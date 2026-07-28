<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantFileService;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'id'            => $tenant->id,
            'name'          => $tenant->name,
            'short_name'    => $tenant->short_name,
            'logo'          => $tenant->logo,
            'logo_url'      => app(TenantFileService::class)->url($tenant->logo),
            'primary_color' => $tenant->primary_color,
            'country'       => $tenant->country,
            'timezone'      => $tenant->timezone,
            'locale'        => $tenant->locale,
            'settings'      => $tenant->settings ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'short_name'    => 'sometimes|string|max:20',
            'primary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'country'       => 'sometimes|string|max:2',
            'timezone'      => 'sometimes|string|max:100',
            'locale'        => 'sometimes|in:fr,en,ar',
            'settings'      => 'sometimes|array',
        ]);

        $tenant = $request->user()->tenant;
        $old = $tenant->toArray();
        $tenant->update($validated);

        $this->auditLog($request, 'settings_updated', Tenant::class, $tenant->id, $old, $tenant->fresh()->toArray());

        return response()->json(['message' => 'Paramètres mis à jour.', 'organisation' => $tenant->fresh()]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $tenant = $request->user()->tenant;

        $path = app(TenantFileService::class)->replace($tenant->logo, $request->file('logo'), 'branding');
        $tenant->update(['logo' => $path]);

        $this->auditLog($request, 'logo_uploaded', Tenant::class, $tenant->id);

        return response()->json([
            'logo'     => $path,
            'logo_url' => app(TenantFileService::class)->url($path),
            'message'  => 'Logo mis à jour.',
        ]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($tenant->logo) {
            app(TenantFileService::class)->delete($tenant->logo);
            $tenant->update(['logo' => null]);
            $this->auditLog($request, 'logo_deleted', Tenant::class, $tenant->id);
        }

        return response()->json(['message' => 'Logo supprimé.']);
    }
}
