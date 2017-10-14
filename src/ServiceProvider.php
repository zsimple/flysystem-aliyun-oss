<?php
namespace Zsimple\Flysystem\AliyunOss;

use Storage;
use OSS\OssClient;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Aliyun Oss ServiceProvider class.
 *
 * @author  xishui <eyehere@sina.com>
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function ($app, $config) {
            $accessId = $config['access_id'];
            $accessKey = $config['access_key'];
            $endPoint = $config['endpoint'];
            $bucket = $config['bucket'];
            $prefix = null;
            if (isset($config['prefix'])) {
                $prefix = $config['prefix'];
            }
            $client = new OssClient($accessId, $accessKey, $endPoint);
            $adapter = new AliyunOssAdapter($client, $bucket, $prefix);
            $filesystem = new Filesystem($adapter);

            return $filesystem;
        });
    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
