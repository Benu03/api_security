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
                $router->post('get-shift-code', 'PresensiController@GetShiftCode');
                $router->post('post-presensi', 'PresensiController@PostPresensi');
                $router->post('get-foto-presensi/{data}', 'PresensiController@GetFotoPresensi');
                $router->post('get-list-jadwal-presensi', 'PresensiController@GetListJadwalPresensi');
                
                #CHECKPOIN PATROLI
                $router->post('post-checkpoint-patroli', 'CheckpointController@PostCheckpointPatroli');
                $router->post('list-checkpoint-patroli', 'CheckpointController@ListCheckpointPatroli');
                
                
                #TEMUAN PATROLI
                $router->post('post-temuan-patroli', 'TemuanController@PostTemuanPatroli');
                $router->post('list-temuan-patroli', 'TemuanController@ListTemuanPatroli');
                $router->post('get-foto-temuan-patroli/{data}', 'TemuanController@GetFotoTemuan');


            });

        });

});