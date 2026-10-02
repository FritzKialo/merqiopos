<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorize(Service $service): void
    {
        abort_if($service->business_id !== $this->businessId(), 403);
    }

    public function index()
    {
        $services = Service::forBusiness($this->businessId())
            ->orderBy('category')->orderBy('name')->get();
        return view('services.index', compact('services'));
    }

    public function create()
    {
        return view('services.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5',
            'price'            => 'required|numeric|min:0',
            'category'         => 'nullable|string|max:100',
            'is_active'        => 'boolean',
        ]);

        Service::create([
            'business_id'      => $this->businessId(),
            'name'             => $request->name,
            'description'      => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'price'            => $request->price,
            'category'         => $request->category,
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return redirect()->route('services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service)
    {
        $this->authorize($service);
        return view('services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize($service);
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5',
            'price'            => 'required|numeric|min:0',
            'category'         => 'nullable|string|max:100',
            'is_active'        => 'boolean',
        ]);

        $service->update([
            'name'             => $request->name,
            'description'      => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'price'            => $request->price,
            'category'         => $request->category,
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return redirect()->route('services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $this->authorize($service);
        $service->delete();
        return back()->with('success', 'Service deleted.');
    }
}
