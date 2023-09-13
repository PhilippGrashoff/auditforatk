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

    //no need to reload audit records
    public bool $reloadAfterSave = false;


    /**
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    protected function init(): void
    {
        parent::init();

        $this->addCreatedDateFieldAndHook();

        //the type of the audit. "FIELD" if a model's field value was changed, other types if it's some other audit
        $this->addField(
            'type',
            ['type' => 'string']
        );

        //If it's an Audit for a field, the old field value is stored in here.
        //the new value is stored in "value" field inherited from SecondaryModel.
        $this->addField(
            'old_value',
            ['type' => 'string']
        );

        /*//If it's an Audit for a field, the new field value is stored in here.
        //If it's another audit type, custom values are stored in here
        $this->addField(
            'value',
            ['type' => 'string']
        );*/

        //store the name of the logged-in user - stored for performance in case rendered_output must be recalculated.
        $this->addField(
            'created_by_name',
            ['type' => 'string']
        );

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
