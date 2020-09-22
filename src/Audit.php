<?php

declare(strict_types=1);

namespace auditforatk;

use atk4\data\Model;
use secondarymodelforatk\SecondaryModel;
use traitsforatkdata\CreatedDateAndLastUpdatedTrait;


class Audit extends SecondaryModel {

    use CreatedDateAndLastUpdatedTrait;

    public $table = 'audit';


    public function init(): void {
        parent::init();

        $this->addCreatedDateAndLastUpdateFields();
        $this->addCreatedDateAndLastUpdatedHook();

        $this->addFields(
            [
                [
                    'data',
                    'type'      => 'array',
                    'serialize' => 'serialize'
                ],
                //store the name of the creator. Might be needed to re-render rendered_output
                [
                    'created_by_name',
                    'type'      => 'string'
                ],
                [
                    'rendered_output',
                    'type'      => 'string'
                ],
            ]
        );


        $this->setOrder('created_date desc');

        // add Name of currently logged in user to "created_by_name" field
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function(Model $model, $isUpdate) {
                if($isUpdate) {
                    return;
                }
                if(
                    isset($model->app->auth->user)
                    && $model->app->auth->user->loaded()
                ) {
                    $model->set('created_by_name', $model->app->auth->user->get('name'));
                }
            }
        );
    }
}
