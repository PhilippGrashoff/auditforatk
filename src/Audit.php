<?php

declare(strict_types=1);

namespace auditforatk;

use Atk4\Data\Model;
use secondarymodelforatk\SecondaryModel;
use traitsforatkdata\CreatedDateAndLastUpdatedTrait;


class Audit extends SecondaryModel
{

    use CreatedDateAndLastUpdatedTrait;

    public $table = 'audit';

    protected $auditRenderer;

    //no need to reload audit records
    public $reload_after_save = false;


    protected function init(): void
    {
        parent::init();

        $this->addCreatedDateAndLastUpdateFields();
        $this->addCreatedDateAndLastUpdatedHook();

        $this->addFields(
            [
                [
                    'data',
                    'type' => 'array',
                    'serialize' => 'serialize'
                ],
                //store the name of the creator. Might be needed to re-render rendered_output
                [
                    'created_by_name',
                    'type' => 'string'
                ],
                [
                    'rendered_output',
                    'type' => 'string'
                ],
            ]
        );


        $this->setOrder(['created_date' => 'desc']);

        // add Name of currently logged in user to "created_by_name" field
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $model, $isUpdate) {
                if (
                    isset($model->persistence->app->auth->user)
                    && $model->persistence->app->auth->user->loaded()
                ) {
                    $model->set('created_by_name', $model->persistence->app->auth->user->get('name'));
                }
            }
        );

        // add possibility to add custom renderer which makes nice messages
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $model, $isUpdate) {
                if ($this->auditRenderer instanceof AuditRendererInterface) {
                    $model->set('rendered_output', $this->auditRenderer->renderMessage($this));
                }
            }
        );
    }
}
