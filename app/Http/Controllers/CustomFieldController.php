<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $businessId     = $this->businessId();
        $productFields  = CustomFieldDefinition::forBusiness($businessId)->where('entity_type', 'product')->orderBy('sort_order')->get();
        $customerFields = CustomFieldDefinition::forBusiness($businessId)->where('entity_type', 'customer')->orderBy('sort_order')->get();
        return view('settings.custom-fields', compact('productFields', 'customerFields'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:product,customer',
            'label'       => 'required|string|max:100',
            'field_type'  => 'required|in:text,number,date,boolean,select',
            'options'     => 'nullable|string',
            'is_required' => 'nullable|boolean',
        ]);

        $businessId = $this->businessId();
        $fieldKey   = Str::snake($request->label);
        $options    = $request->field_type === 'select' && $request->options
            ? array_filter(array_map('trim', explode("\n", $request->options)))
            : null;

        $maxOrder = CustomFieldDefinition::forBusiness($businessId)
            ->where('entity_type', $request->entity_type)
            ->max('sort_order') ?? 0;

        CustomFieldDefinition::create([
            'business_id'  => $businessId,
            'entity_type'  => $request->entity_type,
            'label'        => $request->label,
            'field_key'    => $fieldKey,
            'field_type'   => $request->field_type,
            'options'      => $options,
            'is_required'  => $request->boolean('is_required'),
            'sort_order'   => $maxOrder + 1,
        ]);

        return back()->with('success', 'Custom field added.');
    }

    public function update(Request $request, CustomFieldDefinition $customFieldDefinition)
    {
        abort_if($customFieldDefinition->business_id !== $this->businessId(), 403);
        $request->validate(['label' => 'required|string|max:100', 'is_required' => 'nullable|boolean']);
        $customFieldDefinition->update([
            'label'       => $request->label,
            'is_required' => $request->boolean('is_required'),
            'is_active'   => $request->boolean('is_active', true),
        ]);
        return back()->with('success', 'Field updated.');
    }

    public function destroy(CustomFieldDefinition $customFieldDefinition)
    {
        abort_if($customFieldDefinition->business_id !== $this->businessId(), 403);
        $customFieldDefinition->delete();
        return back()->with('success', 'Field deleted.');
    }

    public function reorder(Request $request)
    {
        $businessId = $this->businessId();
        foreach ($request->input('order', []) as $position => $id) {
            CustomFieldDefinition::where('id', $id)->where('business_id', $businessId)
                ->update(['sort_order' => $position]);
        }
        return response()->json(['ok' => true]);
    }
}
