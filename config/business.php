<?php

return [
    'project' => [
        /*
         * Standard billable hours per month used to convert a monthly rate
         * to an hourly figure (e.g. for worklog cost calculations).
         */
        'monthly_hours' => env('PROJECT_MONTHLY_HOURS', 176),

        /*
         * Maximum rows allowed per bulk import (client + server enforced).
         */
        'max_import_rows' => 200,

        /*
         * Department code used as the default owner department when creating
         * a new project. Falls back to a name-based lookup if not set.
         */
        'default_owner_department_code' => env('DEFAULT_OWNER_DEPT_CODE', null),
    ],
];
