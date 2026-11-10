<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Google\Cloud\Storage\StorageClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('gcs', function ($app, $config) {
            $storageClient = new StorageClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $config['key_file_path'],
            ]);

            $bucket = $storageClient->bucket($config['bucket']);
            
            $visibilityHandler = new \League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility();
            $adapter = new class($bucket, $config['path_prefix'] ?? '', $visibilityHandler) extends GoogleCloudStorageAdapter {
                public function getUrl($path)
                {
                    return $this->publicUrl($path, new \League\Flysystem\Config());
                }
            };

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
