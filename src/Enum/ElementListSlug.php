<?php

namespace App\Enum;

enum ElementListSlug: string
{
    case CLIENT_TYPE = 'tip-client';
    case INDUSTRY = 'industrie';
    case COMPANY_SIZE = 'dimensiune-companie';
    case EQUIPMENT_CATEGORY = 'categorie-echipament';
    case EQUIPMENT_LOCATION = 'locatie-echipament';
    case STORAGE_TYPE = 'tip-stocare';
    case UNIT_OF_MEASURE = 'unitate-masura';
    case SAMPLE_SOURCE = 'sursa-proba';
}
