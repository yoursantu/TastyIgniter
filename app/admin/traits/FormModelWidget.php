<?php

namespace Admin\Traits;

use Admin\Classes\FormField;
use Exception;
use Igniter\Flame\Exception\ApplicationException;
use Illuminate\Support\Facades\Lang;

/**
 * Form Model Widget Trait
 *
 * Special logic for for form widgets that use a database stored model.
 */
trait FormModelWidget
{
    protected $modelsToSave;

    public function createFormModel()
    {
        if (!$this->modelClass) {
            throw new ApplicationException(sprintf(lang('admin::lang.alert_missing_field_property'), get_class($this)));
        }

        $class = $this->modelClass;

        return new $class;
    }

    /**
     * @param $recordId
     * @return \Igniter\Flame\Database\Model
     * @throws \Igniter\Flame\Exception\ApplicationException
     */
    public function findFormModel($recordId)
    {
        $recordId = strip_tags($recordId);
        if (!strlen($recordId)) {
            throw new ApplicationException(lang('admin::lang.form.missing_id'));
        }

        $model = $this->createFormModel();

        // Prepare query and find model record
        $query = $model->newQuery();
        $result = $query->find($recordId);

        if (!$result)
            throw new Exception(sprintf(lang('admin::lang.form.record_not_found_in_model'), $recordId, get_class($model)));

        return $result;
    }

    /**
     * Returns the final model and attribute name of
     * a nested HTML array attribute.
     * Eg: list($model, $attribute) = $this->resolveModelAttribute($this->valueFrom);
     *
     * @param string $attribute .
     *
     * @return array
     */
    public function resolveModelAttribute($attribute = null)
    {
        try {
            return $this->formField->resolveModelAttribute($this->model, $attribute);
        }
        catch (Exception $ex) {
            throw new ApplicationException(Lang::get('backend::lang.model.missing_relation', [
                'class' => get_class($this->model),
                'relation' => $attribute,
            ]));
        }
    }

    /**
     * Returns the model of a relation type.
     * @return \Admin\FormWidgets\Relation
     * @throws \Exception
     */
    protected function getRelationModel()
    {
        [$model, $attribute] = $this->resolveModelAttribute($this->valueFrom);

        if (!$model OR !$model->hasRelation($attribute)) {
            throw new ApplicationException(sprintf(lang('admin::lang.alert_missing_model_definition'),
                get_class($this->model),
                $this->valueFrom
            ));
        }

        return $model->makeRelation($attribute);
    }

    protected function getRelationObject()
    {
        [$model, $attribute] = $this->resolveModelAttribute($this->valueFrom);

        if (!$model OR !$model->hasRelation($attribute)) {
            throw new ApplicationException(sprintf(lang('admin::lang.alert_missing_model_definition'),
                get_class($this->model),
                $this->valueFrom
            ));
        }

        return $model->{$attribute}();
    }

    protected function getRelationType()
    {
        [$model, $attribute] = $this->resolveModelAttribute($this->valueFrom);

        return $model->getRelationType($attribute);
    }

    protected function prepareModelsToSave($model, $saveData)
    {
        $this->modelsToSave = [];
        $this->setModelAttributes($model, $saveData);

        return $this->modelsToSave;
    }

    /**
     * Sets a data collection to a model attributes, relations will also be set.
     *
     * @param \Model $model Model to save to
     *
     * @param array $saveData Data to save.
     *
     * @return void
     */
    protected function setModelAttributes($model, $saveData)
    {
        if (!is_array($saveData) OR !$model) {
            return;
        }

        $this->modelsToSave[] = $model;

        $singularTypes = ['belongsTo', 'hasOne', 'morphTo', 'morphOne'];
        foreach ($saveData as $attribute => $value) {
            $isNested = ($attribute == 'pivot' OR (
                    $model->hasRelation($attribute) AND
                    in_array($model->getRelationType($attribute), $singularTypes)
                ));

            if ($isNested AND is_array($value)) {
                $this->setModelAttributes($model->{$attribute}, $value);
            }
            elseif ($value !== FormField::NO_SAVE_DATA) {
                if (!starts_with($attribute, '_'))
                    $model->{$attribute} = $value;
            }
        }
    }
}
