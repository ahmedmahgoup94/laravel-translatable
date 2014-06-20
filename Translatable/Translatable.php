<?php namespace Dimsav\Translatable;

use App;
use Illuminate\Database\Eloquent\MassAssignmentException;

trait Translatable {

    /**
     * Alias for getTranslation()
     */
    public function translate($locale = null, $defaultLocale = null)
    {
        return $this->getTranslation($locale, $defaultLocale);
    }

    /**
     * Alias for getTranslation()
     */
    public function translateOrDefault($locale)
    {
        return $this->getTranslation($locale, true);
    }

    public function getTranslation($locale = null, $withFallback = false)
    {
        $locale = $locale ?: App::getLocale();

        if ($this->getTranslationByLocaleKey($locale))
        {
            $translation = $this->getTranslationByLocaleKey($locale);
        }
        elseif ($withFallback && $this->getTranslationByLocaleKey(App::getLocale()))
        {
            $translation = $this->getTranslationByLocaleKey(App::getLocale());
        }
        else
        {
            $translation = $this->getNewTranslationInstance($locale);
            $this->translations->add($translation);
        }

        return $translation;
    }

    public function hasTranslation($locale = null)
    {
        $locale = $locale ?: App::getLocale();

        foreach ($this->translations as $translation)
        {
            if ($translation->getAttribute($this->getLocaleKey()) == $locale)
            {
                return true;
            }
        }

        return false;
    }

    public function getTranslationModelName()
    {
        return $this->translationModel ?: $this->getTranslationModelNameDefault();
    }

    public function getTranslationModelNameDefault()
    {
        return get_class($this) . 'Translation';
    }

    public function getRelationKey()
    {
        return $this->translationForeignKey ?: $this->getForeignKey();
    }

    public function getLocaleKey()
    {
        return $this->localeKey ?: 'locale';
    }

    public function translations()
    {
        return $this->hasMany($this->getTranslationModelName(), $this->getRelationKey());
    }

    public function getAttribute($key)
    {
        if ($this->isKeyReturningTranslationText($key))
        {
            return $this->getTranslation()->$key;
        }
       return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->translatedAttributes))
        {
            $this->getTranslation()->$key = $value;
        }
        else
        {
            parent::setAttribute($key, $value);
        }
    }

    public function save(array $options = array())
    {
        if (count($this->getDirty()) > 0)
        {
            if (parent::save($options))
            {
                return $this->saveTranslations();
            }
            return false;
        }
        else
        {
            return $this->saveTranslations();
        }
    }

    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($attributes as $key => $values)
        {
            if ($this->isKeyALocale($key))
            {
                $translation = $this->getTranslation($key);

                foreach ($values as $translationAttribute => $translationValue)
                {
                    if ($this->isFillable($translationAttribute))
                    {
                        $translation->$translationAttribute = $translationValue;
                    }
                    elseif ($totallyGuarded)
                    {
                        throw new MassAssignmentException($key);
                    }
                }
                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    private function getTranslationByLocaleKey($key)
    {
        foreach ($this->translations as $translation)
        {
            if ($translation->getAttribute($this->getLocaleKey()) == $key)
            {
                return $translation;
            }
        }
        return null;
    }

    protected function isKeyReturningTranslationText($key)
    {
        return in_array($key, $this->translatedAttributes);
    }

    protected function isKeyALocale($key)
    {
        $locales = $this->getLocales();
        return in_array($key, $locales);
    }

    protected function getLocales()
    {
        $config = App::make('config');
        return $config->get('app.locales', array());
    }

    protected function saveTranslations()
    {
        $saved = true;
        foreach ($this->translations as $translation)
        {
            if ($saved && $this->isTranslationDirty($translation))
            {
                $translation->setAttribute($this->getRelationKey(), $this->getKey());
                $saved = $translation->save();
            }
        }
        return $saved;
    }

    protected function isTranslationDirty($translation)
    {
        $dirtyAttributes = $translation->getDirty();
        unset($dirtyAttributes[$this->getLocaleKey()]);
        return count($dirtyAttributes) > 0;
    }

    protected function getNewTranslationInstance($locale)
    {
        $modelName = $this->getTranslationModelName();
        $translation = new $modelName;
        $translation->setAttribute($this->getLocaleKey(), $locale);
        return $translation;
    }

}
