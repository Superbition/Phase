<?php

namespace Polyel\Auth\Drivers;

use Polyel\Auth\GenericUser;
use Polyel\Database\Facade\DB;
use Polyel\Encryption\Facade\Crypt;

class Database
{
    // The table name containing the users
    private string $table;

    public function __construct()
    {

    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function table()
    {
        /*
         * If the source table has not been set, use the default table source
         * from the auth config. Here we get the default protector and use the
         * default source set for that protector.
         */
        if(!isset($this->table))
        {
            $defaultProtector = config('auth.defaults.protector');
            $authSource = config("auth.protectors.$defaultProtector.source");
            $this->table = config("auth.sources.$authSource.table");
        }

        return $this->table;
    }

    public function getUserById($id)
    {
        $user = DB::table($this->table())->findById($id);

        return new GenericUser($user);
    }

    public function getUserByCredentials($credentials, $conditions  = null)
    {
        // Only proceed if the credentials exist and that it's not the password that was just sent...
        if(!exists($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials)))
        {
            return false;
        }

        // Start off the query that will be used to search for a user...
        $query = DB::table($this->table());

        /*
         * Using a for loop, build up each credential and
         * add them as a where clause which will be used to
         * search for a user we want to find by their credentials.
         * This is mostly used for when you are logging in a user and
         * trying to find them via an email or username etc.
         */
        foreach($credentials as $key => $value)
        {
            // We don't want to search for a user based on their password as it will be hashed...
            if($key === 'password')
            {
                continue;
            }

            $query->where($key, '=', $value);
        }

        // Add any additional conditions to the query to find the user...
        if(exists($conditions))
        {
            // An extra condition could be used to check if the user is banned or active for example...
            foreach($conditions as $key => $value)
            {
                $query->where($key, '=', $value);
            }
        }

        // Execute the query but only grab the first record, only one record should be found though
        $user = $query->first();

        // Return the database retrieval result based on credentials
        return new GenericUser($user);
    }

    public function getUserByToken($clientId, $conditions  = null)
    {
        // The joining table will be the configured users table
        $joiningTable = $this->table();

        $user = DB::table(config('auth.api_database_token_table'))
            ->join($joiningTable, config('auth.api_database_token_table') . '.user_id', '=', "$joiningTable.id")
            ->where(config('auth.api_database_token_table') . '.id', '=', $clientId)
            ->first();

        /*
         * Remove user_id because we don't want it twice, from the token table (id) and users (id) columns
         * This makes it so the user id is stored as 'id' like it would normally be from its table
         */
        unset($user['user_id']);

        // If a user was found, return a new Generic User instance
        if(exists($user))
        {
            return new GenericUser($user);
        }

        return null;
    }

    public function doesApiClientIdExist($clientId)
    {
        $clientId = DB::table(config('auth.api_database_token_table'))->where('id', '=', $clientId)->first();

        if(exists($clientId))
        {
            return true;
        }

        return false;
    }

    public function createNewApiToken($clientId, $hashedToken, $userId)
    {
        $affected = DB::table(config('auth.api_database_token_table'))->insert([
            'id' => $clientId,
            'token_hashed' => $hashedToken,
            'user_id' => $userId,
            'token_last_active' => null,
            'token_expires_at' => date("Y-m-d H:i:s", strtotime('+' . config('auth.api_token_lifetime'))),
        ]);

        return $affected;
    }

    public function updateApiToken($clientId, $newTokenHash)
    {
        $affected = DB::table(config('auth.api_database_token_table'))
            ->where('id', '=', $clientId)
            ->update([
                'token_hashed' => $newTokenHash,
                'token_last_active' => null,
                'token_expires_at' => date("Y-m-d H:i:s", strtotime('+' . config('auth.api_token_lifetime'))),
            ]);

        return $affected;
    }

    public function deleteApiToken($token)
    {
        DB::table(config('auth.api_database_token_table'))
            ->where('token_hashed', '=', hash_hmac('sha512', $token, Crypt::getEncryptionKey()))
            ->delete();
    }

    public function deleteApiTokenByClientId($clientId)
    {
        DB::table(config('auth.api_database_token_table'))
            ->where('id', '=', $clientId)
            ->delete();
    }

    public function deleteAllApiTokensByUserId($userId)
    {
        DB::table(config('auth.api_database_token_table'))
            ->where('user_id', '=', $userId)
            ->delete();
    }

    public function updateWhenTokenWasLastActive($clientId)
    {
        DB::table(config('auth.api_database_token_table'))->where('id', '=', $clientId)->update([
           'token_last_active' => date("Y-m-d H:i:s"),
        ]);
    }
}