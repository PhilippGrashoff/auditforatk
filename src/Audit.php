<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Core\Exception;
use PhilippR\Atk4\ModelTraits\CreatedDateAndLastUpdatedTrait;
use PhilippR\Atk4\SecondaryModel\SecondaryModel;


class Audit extends SecondaryModel
{

    use CreatedDateAndLastUpdatedTrait;

    public $table = 'audit';

    public const TYPE_FIELD = 'FIELD';
    public const TYPE_CREATED = 'CREATED';
    public const TYPE_DELETED = 'DELETED';

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

        //In case of FIELD audit, the name of the field is stored in here.
        //Other, custom audit types can use this field freely.
        //The main purpose of this field is to easily filter, e.g. only load audits for a certain field.
        $this->addField('ident');

        //In this field all relevant data to re-calculate rendered_output is stored. In case of a FIELD audit,
        //the old and the new value of the field are stored,
        $this->addField('data', ['type' => 'object']);

        //save the user ID for re-rendering
        $this->addField('user_id', ['type' => 'integer']);

        //store the name of the logged-in user - stored directly for performance and in case users are deleted.
        $this->addField('user_name');

        //A text saying what change is audited in this entity. Rendered by a MessageRenderer instance.
        //e.g. changed some_field from "some old value" to "some_new_value"
        //for rendering, the username and the date are usually pulled from the according fields, but
        //you can also put it in here if you please.
        $this->addField('rendered_message', ['type' => 'text']);

        //newest Audits go first
        $this->setOrder(['created_date' => 'desc']);
    }
}
