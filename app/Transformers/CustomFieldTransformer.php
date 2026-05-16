<?php

namespace App\Transformers;

use App\Models\Catalog\CustomField;

class CustomFieldTransformer
{
    public static function transform(CustomField $field): array
    {
        return [
            'id' => $field->id,
            'condominium_id' => $field->condominium_id,
            'entity_type' => $field->entity_type,
            'field_key' => $field->field_key,
            'label' => $field->label,
            'field_type' => $field->field_type,
            'is_required' => $field->is_required,
            'options_catalog_id' => $field->options_catalog_id,
            'sort_order' => $field->sort_order,
            'is_active' => $field->is_active,
        ];
    }
}
