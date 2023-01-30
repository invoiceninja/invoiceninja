<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MultiDatabaseUserProvider implements UserProvider
{
    /**
     * The hasher implementation.
     *
     * @var HasherContract
     */
    protected $hasher;

    /**
     * The Eloquent user model.
     *
     * @var string
     */
    protected $model;

    /**
     * Create a new database user provider.
     *
     * @param HasherContract $hasher
     * @param  string  $model
     * @return void
     */
    public function __construct(HasherContract $hasher, $model)
    {
        $this->model = $model;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return UserContract|null
     */
    public function retrieveById($identifier)
    {
        $this->setDefaultDatabase($identifier);

        $model = $this->createModel();

        return $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return UserContract|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $this->setDefaultDatabase($identifier, $token);

        $model = $this->createModel();

        $model = $model->where($model->getAuthIdentifierName(), $identifier)->first();

        if (! $model) {
            return null;
        }

        $rememberToken = $model->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $model : null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param UserContract|Model  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, $token)
    {
        $user->setRememberToken($token);

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return UserContract|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) ||
           (count($credentials) === 1 &&
            array_key_exists('password', $credentials))) {
            return;
        }

        $this->setDefaultDatabase(false, $credentials['email'], false);

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // Eloquent User "model" that will be utilized by the Guard instances.
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (Str::contains($key, 'password')) {
                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param UserContract $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Create a new instance of the model.
     *
     * @return Model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the hasher implementation.
     *
     * @return HasherContract
     */
    public function getHasher()
    {
        return $this->hasher;
    }

    /**
     * Sets the hasher implementation.
     *
     * @param HasherContract $hasher
     * @return $this
     */
    public function setHasher(HasherContract $hasher)
    {
        $this->hasher = $hasher;

        return $this;
    }

    /**
     * Gets the name of the Eloquent user model.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model.
     *
     * @param  string  $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Sets correct database by variable.
     * @param bool $id
     * @param bool $email
     * @param bool $token
     */
    private function setDefaultDatabase($id = false, $email = false, $token = false) : void
    {
        foreach (MultiDB::getDbs() as $database) {
            $this->setDB($database);

            /** Make sure we hook into the correct guard class */
            $query = $this->conn->table((new $this->model)->getTable());

            if ($id) {
                $query->where('id', '=', $id);
            }

            if ($email) {
                $query->where('email', '=', $email);
            }

            $user = $query->get();

            if (count($user) >= 1) {
                break;
            }

            $query = $this->conn->table('company_tokens');

            if ($token) {
                $query->whereRaw('BINARY `token`= ?', $token);

                $token = $query->get();

                if (count($token) >= 1) {
                    break;
                }
            }
        }
    }

    /**
     * Sets the database at runtime.
     * @param $database
     */
    private function setDB($database)
    {
        /** Get the database name we want to switch to*/
        $db_name = config('database.connections.'.$database.'.database');

        /* This will set the default configuration for the request / session?*/
        config(['database.default' => $database]);

        /* Set the connection to complete the user authentication */
        $this->conn = app('db')->connection(config('database.connections.database.'.$database));
    }
}
