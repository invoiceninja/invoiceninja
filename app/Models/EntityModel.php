<?php namespace App\Models;

use Auth;
use Eloquent;
use Session;
use Utils;

/**
 * Class EntityModel
 */
class EntityModel extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = true;
    /**
     * @var array
     */
    protected $hidden = ['id'];

    /**
     * @var bool
     */
    public static $notifySubscriptions = true;

    /**
     * @param null $context
     * @return mixed
     */
    public static function createNew($context = null)
    {
        $className = get_called_class();
        $entity = new $className();

        if ($context) {
            $user = $context instanceof User ? $context : $context->user;
            $account = $context->account;
        } elseif (Auth::check()) {
            $user = Auth::user();
            $account = Auth::user()->account;
        } else {
            Utils::fatalError();
        }

        $entity->user_id = $user->id;
        $entity->account_id = $account->id;

        // store references to the original user/account to prevent needing to reload them
        $entity->setRelation('user', $user);
        $entity->setRelation('account', $account);

        if (method_exists($className, 'trashed')){
            $lastEntity = $className::whereAccountId($entity->account_id)->withTrashed();
        } else {
            $lastEntity = $className::whereAccountId($entity->account_id);
        }

        $lastEntity = $lastEntity->orderBy('public_id', 'DESC')
                        ->first();

        if ($lastEntity) {
            $entity->public_id = $lastEntity->public_id + 1;
        } else {
            $entity->public_id = 1;
        }

        return $entity;
    }

    /**
     * @param $publicId
     * @return mixed
     */
    public static function getPrivateId($publicId)
    {
        $className = get_called_class();

        return $className::scope($publicId)->withTrashed()->value('id');
    }

    /**
     * @return string
     */
    public function getActivityKey()
    {
        return '[' . $this->getEntityType().':'.$this->public_id.':'.$this->getDisplayName() . ']';
    }

    /**
     * @param $query
     * @param bool $publicId
     * @param bool $accountId
     * @return mixed
     */
    public function scopeScope($query, $publicId = false, $accountId = false)
    {
        if (!$accountId) {
            $accountId = Auth::user()->account_id;
        }

        $query->where($this->getTable() .'.account_id', '=', $accountId);

        if ($publicId) {
            if (is_array($publicId)) {
                $query->whereIn('public_id', $publicId);
            } else {
                $query->wherePublicId($publicId);
            }
        }

        if (Auth::check() && ! Auth::user()->hasPermission('view_all')) {
            $query->where(Utils::pluralizeEntityType($this->getEntityType()) . '.user_id', '=', Auth::user()->id);
        }

        return $query;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeWithArchived($query)
    {
        return $query->withTrashed()->where('is_deleted', '=', false);
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->public_id;
    }

    /**
     * @return mixed
     */
    public function getDisplayName()
    {
        return $this->getName();
    }

    /**
     * @param $entityType
     * @return string
     */
    public static function getClassName($entityType)
    {
        return 'App\\Models\\' . ucwords(Utils::toCamelCase($entityType));
    }

    /**
     * @param $entityType
     * @return string
     */
    public static function getTransformerName($entityType)
    {
        return 'App\\Ninja\\Transformers\\' . ucwords(Utils::toCamelCase($entityType)) . 'Transformer';
    }

    public function setNullValues()
    {
        foreach ($this->fillable as $field) {
            if (strstr($field, '_id') && !$this->$field) {
                $this->$field = null;
            }
        }
    }

    // converts "App\Models\Client" to "client_id"
    /**
     * @return string
     */
    public function getKeyField()
    {
        $class = get_class($this);
        $parts = explode('\\', $class);
        $name = $parts[count($parts)-1];
        return strtolower($name) . '_id';
    }

    /**
     * Load localization settings explicitly for a specific client
     *
     * @param Client $client
     */
    public function loadLocalizationSettingsForClient(Client $client)
    {
        $this->load('language');

        $locale = $client->language_id
            ? $client->language->locale
            : $this->getLocale();

        $currencyId = $client->currency_id ?: $this->getCurrencyId();

        Session::put(SESSION_CURRENCY, $currencyId);
        $this->setApplicationLocale($locale);
    }

    /**
     * Load localisation settings and put it into the session
     */
    public function loadLocalizationSettings()
    {
        $this->load('timezone', 'date_format', 'datetime_format', 'language');

        Session::put(SESSION_TIMEZONE, $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE);
        Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);

        Session::put(
            SESSION_DATE_PICKER_FORMAT,
            $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT
        );

        Session::put(SESSION_DATETIME_FORMAT, $this->getDateTimeFormat());
        Session::put('start_of_week', $this->start_of_week);
        Session::put(SESSION_CURRENCY, $this->getCurrencyId());
        Session::put('language', $this->getLanguage());

        $this->setApplicationLocale($this->getLocale());
    }

    /**
     * Get the currency id, if set. Otherwise return the default currency id.
     *
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->currency_id ?: DEFAULT_CURRENCY;
    }

    /**
     * Get the datetime format from the database. Also handles if military time is used.
     *
     * @return string
     */
    protected function getDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;

        if ($this->military_time) {
            $count = 1;
            // Replace only first occurence to only return a string, not an array
            $format = str_replace('g:i a', 'H:i', $format, $count);
        }

        return $format;
    }

    /**
     * Get the locale for either the account or the client.
     *
     * @return string
     */
    protected function getLocale()
    {
        return $this->language_id ? $this->language->locale : DEFAULT_LOCALE;
    }

    /**
     * Extract language code from locale.
     * The locale format is <language code>_<region code>.
     *
     * Use the {@link getLanguage()} method to the the correct language code.
     *
     * @return  string
     */
    private function getLanguageCodeFromLocale()
    {
        $code = $this->getLocale();

        if(preg_match('/_/', $code)) {
            $codes = explode('_', $this->getLocale());
            $code = $codes[0];
        }

        return $code;
    }

    /**
     * Extract region code from locale.
     * The locale format is <language code>_<region code>.
     *
     * * Use the {@link getRegion()} method to the the correct language code.
     *
     * @return string
     */
    private function getRegionCodeFromLocale()
    {
        $code = $this->getLocale();

        if(preg_match('/_/', $code)) {
            $codes = explode('_', $this->getLocale());
            $code = $codes[1];
        }

        return $code;
    }

    /**
     * Set the application locale if a new locale is specified
     *
     * @param $locale
     */
    private function setApplicationLocale($locale)
    {
        if(!app()->isLocale($locale)) {

            app()->setLocale($locale);
        }
    }

    /**
     * Get the region code setting, i. e. 'DE', 'US', ...
     *
     * @return string
     */
    protected function getRegion()
    {
        return $this->getRegionCodeFromLocale();
    }

    /**
     * Get the language code setting, i. e. 'de', 'en', ...
     *
     * @return string
     */
    protected function getLanguage()
    {
        return $this->getLanguageCodeFromLocale();
    }
}
