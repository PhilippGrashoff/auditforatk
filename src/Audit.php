<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use PhilippR\Atk4\ModelTraits\CreatedDateAndLastUpdatedTrait;
use PhilippR\Atk4\SecondaryModel\SecondaryModel;


class Audit extends SecondaryModel
{

    use CreatedDateAndLastUpdatedTrait;

    public $table = 'audit';

    protected ?AuditRendererInterface $auditRenderer = null;

    /**
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    protected function init(): void
    {
        parent::init();

        //add created date field and hook to automatically set value.
        $this->addCreatedDateFieldAndHook();

        //the type of the audit, can be freely defined.
        // "FIELD" if a model's field value was changed,
        // "CREATED" if a model record was created,
        // "DELETED" if a model record was deleted,
        // other types if it's some other audit
        $this->addField('type');

        //In this field all relevant data to calculate rendered_output is stored. In case of a field audit, it is
        //fieldName, oldValue and newValue,
        $this->addField(
            'data',
            ['type' => 'json']
        );

        //store the name of the logged-in user - stored directly for performance and in case users are deleted.
        $this->addField('created_by_name');

        //A text saying what change is audited in this entity. Rendered by $this->auditRenderer.
        // E.g. 2023-05-22 12:55 Some User changed some_field from "some old value" to "some_new_value"
        $this->addField(
            'rendered_output',
            ['type' => 'text']
        );

        //newest Audits go first
        $this->setOrder(['created_date' => 'desc']);

        // add Name of currently logged-in user to "created_by_name" field
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $model) {
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
            function (self $model) {
                if ($this->auditRenderer instanceof AuditRendererInterface) {
                    $model->set('rendered_output', $this->auditRenderer->renderMessage($this));
                }
            }
        );
    }
}
