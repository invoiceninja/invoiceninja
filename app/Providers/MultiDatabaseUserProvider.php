<?php

namespace App\Providers;

use App\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MultiDatabaseUserProvider implements UserProvider
{
    /**
     * The active database connection.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $conn;

    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The table containing the users.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param \Illuminate\Contracts\Hashing\Hasher $hasher
     * @param string                               $table
     *
     * @return void
     */
    public function __construct(ConnectionInterface $conn, HasherContract $hasher, $table = 'users')
    {
        $this->conn = $conn;
        $this->table = $table;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $this->setDefaultDatabase($identifier);

        $user = $this->conn->table($this->table)->find($identifier);

        return $this->getGenericUser($user);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param mixed  $identifier
     * @param string $token
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $this->setDefaultDatabase($identifier, false, $token);

        $user = $this->conn->table($this->table)
                        ->where('id', $identifier)
                        ->where('remember_token', $token)
                        ->first();

        return $this->getGenericUser($user);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param string                                     $token
     *
     * @return void
     */
    public function updateRememberToken(UserContract $user, $token)
    {
        $this->conn->table($this->table)
                ->where('id', $user->getAuthIdentifier())
                ->update(['remember_token' => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // generic "user" object that will be utilized by the Guard instances.
        $query = $this->conn->table($this->table);

        foreach ($credentials as $key => $value) {
            if (!Str::contains($key, 'password')) {
                $this->setDefaultDatabase(false, $value, false);
                $query->where($key, $value);
            }
        }

        // Now we are ready to execute the query to see if we have an user matching
        // the given credentials. If not, we will just return nulls and indicate
        // that there are no matching users for these given credential arrays.
        $user = $query->first();

        return $this->getGenericUser($user);
    }

    /**
     * Get the generic user.
     *
     * @param mixed $user
     *
     * @return \Illuminate\Auth\GenericUser|null
     */
    protected function getGenericUser($user)
    {
        if (!is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param array                                      $credentials
     *
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        return $this->hasher->check(
            $credentials['password'], $user->getAuthPassword()
        );
    }

    /**
     * @param (int)  $id
     * @param string $username
     * @param string $token
     *
     * @return void
     */
    private function setDefaultDatabase($id = false, $username = false, $token = false) : void
    {
        $databases = ['db-ninja-1', 'db-ninja-2'];

        foreach ($databases as $database) {

            $this->setDB($database);
            //Log::error('database name = '. DB::getDatabaseName());

            $query = $this->conn->table('users');

            if ($id) {
                $query->where('id', '=', $id);
            }

            if ($token) {
                $query->where('token', '=', $token);
            }

            if ($username) {
                $query->where('username', '=', $username);
            }

            $user = $query->get();
            
          //  Log::error(print_r($user,1));
          //  Log::error($database);

            if (count($user) >= 1) {
                    Log::error('found a DB!');
                    break;
            }
        }
    }

    private function setDB($database)
    {
       // DB::disconnect('db-ninja-1');
       // DB::purge('db-ninja-1');
       // DB::purge('db-ninja-2');
       // DB::purge('default');

        $db_name = config("database.connections.".$database.".database");


        config(['database.default' => $database]);

        //Config::set('database.connections.default.database', Config::get('database.connections.' . $database . '.database'));
        $this->conn = app('db')->connection(config("database.connections.database.".$database.".".$db_name));
      //  $this->conn = DB::connection(config("database.connections.".$database));
    
        //DB::connection(config("database.connections.database.".$database.".".$db_name));

        Log::error('if this works the new DB name should = '. Config::get('database.connections.' . $database . '.database') .' does it ? = '. DB::getDatabaseName());

        /*
        Log::error('trying to make connection for ' . $database);
        $config = App::make('config');

        // Will contain the array of connections that appear in our database config file.
        $connections = $config->get('database.connections');

        // This line pulls out the default connection by key (by default it's `mysql`)
        $defaultConnection = $connections[$config->get('database.default')];

        // Now we simply copy the default connection information to our new connection.
        $newConnection = $defaultConnection;

        // Override the database name.
        $newConnection['database'] = Config::get('database.connections.' . $database . '.database');

        // This will add our new connection to the run-time configuration for the duration of the request.
        App::make('config')->set('database.connections.default', $newConnection);

        $this->conn = app('db')->connection();

        DB::connection($database);

        Log::error('if this works the new DB name should = '. $newConnection['database'] .' does it ? = '. DB::getDatabaseName());
        */
    }
}
