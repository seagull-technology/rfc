<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ScoutingRequest;

class WorkflowMessageMetadata
{
    /**
     * @return array<string, string>
     */
    public static function application(FilmApplication $application): array
    {
        $checkpoint = AdminWorkflowState::applicationCheckpoint($application);

        return [
            'workflow_checkpoint_key' => $checkpoint['key'],
            'workflow_checkpoint_label' => $checkpoint['label'],
            'workflow_checkpoint_class' => $checkpoint['class'],
            'workflow_station_label' => __('app.contact_center.stations.production_request'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function scouting(ScoutingRequest $requestRecord): array
    {
        $checkpoint = AdminWorkflowState::scoutingCheckpoint($requestRecord);

        return [
            'workflow_checkpoint_key' => $checkpoint['key'],
            'workflow_checkpoint_label' => $checkpoint['label'],
            'workflow_checkpoint_class' => $checkpoint['class'],
            'workflow_station_label' => __('app.contact_center.stations.scouting_request'),
        ];
    }
}
