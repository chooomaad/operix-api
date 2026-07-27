<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Traits\HasTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use HasTenantScope;

    public function index(): JsonResponse
    {
        $org = Organisation::first() ?? new Organisation();

        return response()->json([
            'id'            => $org->id,
            'name'          => $org->name,
            'short_name'    => $org->short_name,
            'logo'          => $org->logo,
            'logo_url'      => $org->logo ? Storage::disk('public')->url($org->logo) : null,
            'primary_color' => $org->primary_color,
            'country'       => $org->country,
            'timezone'      => $org->timezone,
            'locale'        => $org->locale,
            'settings'      => $org->settings ?? [],
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

        $org = Organisation::firstOrCreate([]);
        $old = $org->toArray();
        $org->update($validated);

        $this->auditLog($request, 'settings_updated', Organisation::class, $org->id, $old, $org->fresh()->toArray());

        return response()->json(['message' => 'Paramètres mis à jour.', 'organisation' => $org->fresh()]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $org = Organisation::firstOrCreate([]);

        if ($org->logo) {
            Storage::disk('public')->delete($org->logo);
        }

        $path = $request->file('logo')->store('operix/branding', 'public');
        $org->update(['logo' => $path]);

        $this->auditLog($request, 'logo_uploaded', Organisation::class, $org->id);

        return response()->json([
            'logo'     => $path,
            'logo_url' => Storage::disk('public')->url($path),
            'message'  => 'Logo mis à jour.',
        ]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $org = Organisation::first();

        if ($org && $org->logo) {
            Storage::disk('public')->delete($org->logo);
            $org->update(['logo' => null]);
            $this->auditLog($request, 'logo_deleted', Organisation::class, $org->id);
        }

        return response()->json(['message' => 'Logo supprimé.']);
    }
}
