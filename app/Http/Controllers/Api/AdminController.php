<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The shop team. The super admin is invisible here: nobody can list, edit or
 * delete it through the API (it is managed by AdminSeeder only).
 */
class AdminController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return AdminResource::collection(Admin::regular()->orderBy('id')->get());
    }

    public function store(AdminRequest $request)
    {
        $data = $request->validated();

        // "role" is not fillable: every account created here is a regular admin
        $admin = Admin::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'active' => (bool) $data['active'],
            'password' => $data['password'],
        ]);

        $this->log->record(
            $request->user(), 'create', 'admin', $admin->id,
            "Administrateur « {$admin->name} » créé",
        );

        return (new AdminResource($admin))->response()->setStatusCode(201);
    }

    public function update(AdminRequest $request, Admin $admin)
    {
        abort_if($admin->isSuperAdmin(), 404);

        $actor = $request->user();
        $data = $request->validated();
        $active = (bool) $data['active'];
        $newPassword = $data['password'] ?? null;

        // A password can be changed by its owner, or by the super admin. Nobody else.
        if (! empty($newPassword) && ! $actor->isSuperAdmin() && ! $admin->is($actor)) {
            return response()->json([
                'message' => 'Seul le super administrateur peut changer le mot de passe d\'un autre compte.',
            ], 403);
        }

        if ($admin->active && ! $active) {
            if ($admin->is($actor)) {
                throw ValidationException::withMessages([
                    'active' => 'Vous ne pouvez pas désactiver votre propre compte.',
                ]);
            }
            $this->ensureAnotherActive($admin);
        }

        $admin->name = $data['name'];
        $admin->phone = $data['phone'];
        $admin->active = $active;

        $revoke = ! $active;
        $passwordChanged = ! empty($newPassword);
        if ($passwordChanged) {
            $admin->password = $newPassword;
            $revoke = true;
        }
        $admin->save();

        // Deactivated or new password: old tokens stop working
        if ($revoke) {
            $admin->tokens()->delete();
        }

        $this->log->record(
            $actor, 'update', 'admin', $admin->id,
            sprintf(
                'Administrateur « %s » modifié (%s)%s',
                $admin->name,
                $active ? 'actif' : 'désactivé',
                $passwordChanged ? ', mot de passe changé' : '',
            ),
        );

        return new AdminResource($admin);
    }

    public function destroy(Request $request, Admin $admin)
    {
        abort_if($admin->isSuperAdmin(), 404);

        if ($admin->is($request->user())) {
            throw ValidationException::withMessages([
                'admin' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        if ($admin->active) {
            $this->ensureAnotherActive($admin);
        }

        $name = $admin->name;
        $id = $admin->id;

        $admin->tokens()->delete();
        $admin->delete();

        $this->log->record(
            $request->user(), 'delete', 'admin', $id,
            "Administrateur « {$name} » supprimé",
        );

        return response()->noContent();
    }

    /** The shop must always keep one active regular admin (the super admin does not count). */
    private function ensureAnotherActive(Admin $admin): void
    {
        $other = Admin::regular()
            ->where('active', true)
            ->where('id', '!=', $admin->id)
            ->exists();

        if (! $other) {
            throw ValidationException::withMessages([
                'admin' => 'Il doit rester au moins un administrateur actif.',
            ]);
        }
    }
}