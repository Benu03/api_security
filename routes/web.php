<?php



$router->group(['middleware' => 'key_service'], function () use ($router) 
{
        $router->group(['prefix' => 'api/'], function () use ($router) 
        {

            $router->group(['prefix' => 'v1/'], function () use ($router) 
            {

                #MAIN
                $router->post('get-users-access', 'MainController@UserAccess');
                $router->post('sync-users-access', 'MainController@SyncUseraccess');



                #PRESENSI
                $router->post('post-presensi', 'PresensiController@PostPresensi');
                
                #CHECKPOIN PATROLI
                $router->post('post-checkpoint-patroli', 'CheckpointController@PostCheckpointPatroli');

                #TEMUAN PATROLI

            });

        });

});