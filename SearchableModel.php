<?php

namespace Platform\Models;

interface SearchableModel
{
    const TYPE_INT = 'int';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_STRING = 'string';

    /**
     * Gets the searchable fields array.
     *
     * This may also include relationships to other searchable models.
     * In the case of a relationship, the name will be treated as a prefix.
     *
     * @return array
     */
    public function searchableFields();
}
