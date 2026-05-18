<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu\Menu;
use App\Transformers\MenuTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->abortUnlessSeniorAdmin($request);

        $menus = Menu::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $this->responder
            ->success($menus, [MenuTransformer::class, 'transform'])
            ->message('Menus obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessSeniorAdmin($request);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:menus,id'],
            'code' => ['required', 'string', 'max:120', 'unique:menus,code'],
            'label' => ['required', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'required_role' => ['nullable', 'string', 'max:80'],
            'required_permission' => ['nullable', 'string', 'max:80'],
        ]);

        $menu = Menu::query()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->responder
            ->success($menu->load('children'), [MenuTransformer::class, 'transform'], 201)
            ->message('Menu creado correctamente.')
            ->respond();
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $this->abortUnlessSeniorAdmin($request);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:menus,id'],
            'code' => ['sometimes', 'string', 'max:120', Rule::unique('menus', 'code')->ignore($menu)],
            'label' => ['sometimes', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'required_role' => ['nullable', 'string', 'max:80'],
            'required_permission' => ['nullable', 'string', 'max:80'],
        ]);

        if (($data['parent_id'] ?? null) === $menu->id) {
            abort(422, 'Un menu no puede ser padre de si mismo.');
        }

        $menu->fill($data)->save();

        return $this->responder
            ->success($menu->load('children'), [MenuTransformer::class, 'transform'])
            ->message('Menu actualizado correctamente.')
            ->respond();
    }

    private function abortUnlessSeniorAdmin(Request $request): void
    {
        if (! $request->user()?->isSeniorAdmin()) {
            abort(403, 'Solo el administrador senior puede administrar menus.');
        }
    }
}
