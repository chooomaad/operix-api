<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Mail\OtpMail;
use App\Models\OtpToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $email = strtolower($request->email);

        $user = User::where('email', $email)->where('is_active', true)->first();
        if (!$user) {
            return response()->json(['message' => 'Aucun compte actif trouvé pour cet email.'], 404);
        }

        if ($failure = $this->tenantAuthFailure($user)) {
            return $failure;
        }

        OtpToken::where('email', $email)->where('used', false)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpToken::create([
            'email'      => $email,
            'token'      => $code,
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(5),
            'used'       => false,
        ]);

        Mail::to($email)->send(new OtpMail($code, $user->name));

        return response()->json([
            'message' => 'Code envoyé à ' . $email . '. Valable 5 minutes.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $email = strtolower($request->email);
        $code  = $request->code;

        $otp = OtpToken::where('email', $email)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        if ($otp->attempts >= 3) {
            $otp->delete();
            return response()->json(['message' => 'Trop de tentatives. Demandez un nouveau code.'], 429);
        }

        if ($otp->token !== $code) {
            $otp->increment('attempts');
            $remaining = 3 - $otp->attempts;
            return response()->json([
                'message'   => 'Code incorrect.',
                'remaining' => $remaining,
            ], 422);
        }

        $otp->update(['used' => true]);

        $user = User::where('email', $email)->where('is_active', true)->first();
        if (!$user) {
            return response()->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($failure = $this->tenantAuthFailure($user)) {
            return $failure;
        }

        $token = $this->issuePlatformToken($user, $request);
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token'        => $token,
            'user'         => $this->formatUser($user),
            'tenant'       => $this->tenantInfo($user),
            'abilities'    => $this->abilitiesFor($user),
            'organisation' => $this->orgInfo($user),
        ]);
    }

    public function loginWithMatricule(Request $request): JsonResponse
    {
        $request->validate([
            'matricule' => ['required', 'string'],
            'pin'       => ['required', 'string', 'min:4', 'max:50'],
        ]);

        $user = User::whereRaw('LOWER(matricule) = ?', [strtolower($request->matricule)])->first();

        if (!$user || !Hash::check($request->pin, $user->password)) {
            return response()->json(['message' => 'Matricule ou PIN incorrect.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Votre compte est en attente de validation par l\'administrateur.'], 403);
        }

        if ($failure = $this->tenantAuthFailure($user)) {
            return $failure;
        }

        $token = $this->issuePlatformToken($user, $request);
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token'        => $token,
            'user'         => $this->formatUser($user),
            'tenant'       => $this->tenantInfo($user),
            'abilities'    => $this->abilitiesFor($user),
            'organisation' => $this->orgInfo($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user'         => $this->formatUser($user),
            'tenant'       => $this->tenantInfo($user),
            'abilities'    => $this->abilitiesFor($user),
            'organisation' => $this->orgInfo($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'nom'       => ['required', 'string', 'max:100'],
            'prenom'    => ['required', 'string', 'max:100'],
            'matricule' => ['required', 'string', 'max:50', 'unique:users,matricule'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'pin'       => ['required', 'string', 'min:4', 'max:50', 'confirmed'],
        ]);

        $tenant = Tenant::where('slug', 'tcn')->first();
        if (! $tenant) {
            return response()->json([
                'message' => 'Les inscriptions sont temporairement indisponibles.',
            ], 503);
        }

        User::create([
            'tenant_id' => $tenant->id,
            'name'      => trim($request->prenom . ' ' . $request->nom),
            'email'     => strtolower($request->email),
            'matricule' => strtoupper($request->matricule),
            'password'  => Hash::make($request->pin),
            'role'      => 'agent',
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Demande de compte créée. Un administrateur validera votre accès.',
        ], 201);
    }

    public function forgotPin(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = strtolower($request->email);
        $user  = User::where('email', $email)->where('is_active', true)->first();

        if (!$user) {
            return response()->json(['message' => 'Si cet email existe, un code vous sera envoyé.']);
        }

        if ($failure = $this->tenantAuthFailure($user)) {
            return $failure;
        }

        OtpToken::where('email', $email)->where('used', false)->delete();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpToken::create([
            'email'      => $email,
            'token'      => $code,
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10),
            'used'       => false,
        ]);

        Mail::to($email)->send(new OtpMail($code, $user->name));

        return response()->json([
            'message' => 'Code de réinitialisation envoyé à ' . $email . '.',
        ]);
    }

    public function resetPin(Request $request): JsonResponse
    {
        $request->validate([
            'email'   => ['required', 'email'],
            'code'    => ['required', 'string', 'size:6'],
            'new_pin' => ['required', 'string', 'min:4', 'max:50', 'confirmed'],
        ]);

        $email = strtolower($request->email);

        $otp = OtpToken::where('email', $email)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || $otp->attempts >= 3) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        if ($otp->token !== $request->code) {
            $otp->increment('attempts');
            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        $otp->update(['used' => true]);

        $user = User::where('email', $email)->where('is_active', true)->first();
        if (!$user) {
            return response()->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($failure = $this->tenantAuthFailure($user)) {
            return $failure;
        }

        $user->update(['password' => Hash::make($request->new_pin)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'PIN réinitialisé avec succès. Vous pouvez vous connecter.']);
    }

    /**
     * Plateformes clientes autorisées à ouvrir une session.
     *
     * Liste blanche stricte : le nom du jeton provient du client, il ne doit jamais
     * être une chaîne libre (sinon un appelant pourrait forger un nom arbitraire et
     * échapper à la révocation ciblée, ou usurper le nom d'une autre plateforme).
     */
    private const PLATFORMS = ['web', 'mobile'];

    /**
     * Émet un jeton d'accès rattaché à UNE plateforme.
     *
     * Auparavant, chaque connexion exécutait `$user->tokens()->delete()` : ouvrir la
     * session mobile déconnectait la session web du même utilisateur, et inversement.
     * Acceptable pour un client unique, rédhibitoire dès qu'un second client existe —
     * un responsable HSE passant du bureau au terrain entrait dans une boucle de
     * reconnexions (voir docs/MOBILE_API_READINESS.md §B1).
     *
     * On ne révoque donc que les jetons de la plateforme concernée : une nouvelle
     * connexion mobile invalide l'ancienne session mobile (comportement attendu :
     * un téléphone perdu ne garde pas d'accès) sans toucher à la session web.
     *
     * Les révocations globales restent volontairement en place là où elles ont un sens
     * de sécurité : réinitialisation du PIN et suppression de compte.
     */
    private function issuePlatformToken(User $user, Request $request): string
    {
        $platform = strtolower((string) $request->input('platform', 'web'));

        if (! in_array($platform, self::PLATFORMS, true)) {
            $platform = 'web';
        }

        $name = "operix-{$platform}";

        $user->tokens()->where('name', $name)->delete();

        return $user->createToken($name)->plainTextToken;
    }

    private function formatUser(User $u): array
    {
        return [
            'id'           => $u->id,
            'name'         => $u->name,
            'email'        => $u->email,
            'role'         => $u->role,
            'matricule'    => $u->matricule,
            'phone'        => $u->phone,
            'avatar'       => $u->avatar,
            'is_active'    => $u->is_active,
            'last_login_at'=> $u->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * Identité du tenant destinée aux clients.
     *
     * Distinct de orgInfo() (branding pur) : porte l'`id`, dont un client a besoin
     * pour cloisonner ses caches locaux entre deux comptes de tenants différents sur
     * le même appareil.
     */
    private function tenantInfo(?User $user): ?array
    {
        $tenant = $user?->tenant;

        if (! $tenant) {
            return null;
        }

        return [
            'id'            => $tenant->id,
            'name'          => $tenant->name,
            'short_name'    => $tenant->short_name,
            'logo_url'      => app(\App\Services\TenantFileService::class)->url($tenant->logo),
            'primary_color' => $tenant->primary_color,
            'locale'        => $tenant->locale,
            'country'       => $tenant->country,
            'timezone'      => $tenant->timezone,
        ];
    }

    /**
     * Permissions effectives de l'utilisateur.
     *
     * Le client (web ou mobile) s'en sert UNIQUEMENT pour masquer, désactiver ou
     * rediriger : le backend reste seul juge, chaque route porte sa propre permission.
     * Sans cette liste, un client devrait redéclarer la table des rôles du serveur et
     * finirait par s'en désynchroniser.
     *
     * @return list<string>
     */
    private function abilitiesFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    private function orgInfo(?User $user = null): array
    {
        $tenant = $user?->tenant ?? Tenant::where('slug', 'tcn')->first();

        return [
            'name'          => $tenant?->name          ?? 'Terminal à Conteneurs de Nouakchott',
            'short_name'    => $tenant?->short_name     ?? 'TCN',
            'logo_url'      => app(\App\Services\TenantFileService::class)->url($tenant?->logo),
            'primary_color' => $tenant?->primary_color  ?? '#0f2847',
            'locale'        => $tenant?->locale          ?? 'fr',
            'country'       => $tenant?->country         ?? 'MR',
            'timezone'      => $tenant?->timezone        ?? 'Africa/Nouakchott',
        ];
    }

    private function tenantAuthFailure(User $user): ?JsonResponse
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Votre compte n\'est rattache a aucune entreprise.'], 403);
        }

        if ($tenant->isSuspended()) {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez support@operix-app.com',
            ], 403);
        }

        if (! $tenant->allowsApplicationAccess()) {
            return response()->json(['message' => 'Votre acces a cette entreprise a expire.'], 403);
        }

        return null;
    }
}
