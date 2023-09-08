<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Model;
use atkdatamodeltraits\CreatedDateAndLastUpdatedTrait;
use secondarymodelforatk\SecondaryModel;


class Audit extends SecondaryModel
{

    use CreatedDateAndLastUpdatedTrait;

    public $table = 'audit';

    protected AuditRendererInterface $auditRenderer;

    //no need to reload audit records
    public bool $reloadAfterSave = false;


    protected function init(): void
    {
        parent::init();

        $this->addCreatedDateFieldAndHook();

        $this->addField(
            'data',
            ['type' => 'json']
        );

        //store the name of the creator for performance. Might be needed to re-render rendered_output
        $this->addField(
            'created_by_name',
            ['type' => 'string']
        );

        $this->addField(
            'rendered_output',
            ['type' => 'text']
        );

        $this->setOrder(['created_date' => 'desc']);

        // add Name of currently logged-in user to "created_by_name" field
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
