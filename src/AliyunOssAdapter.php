<?php

namespace Zsimple\Flysystem\AliyunOss;

use OSS\OssClient;
use OSS\Core\OssException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;

/**
 * Aliyun Oss Adapter class.
 *
 * @author  ApolloPY <ApolloPY@Gmail.com>
 */
class AliyunOssAdapter extends AbstractAdapter implements CanOverwriteFiles
{
    /**
     * Aliyun Oss Client.
     *
     * @var \OSS\OssClient
     */
    protected $client;

    /**
     * bucket name.
     *
     * @var string
     */
    protected $bucket;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected static $resultMap = [
        'Body'          => 'contents',
        'ContentLength' => 'size',
        'ContentType'   => 'mimetype',
        'Size'          => 'size',
        'Metadata'      => 'metadata',
    ];

    /**
     * @var array
     */
    protected static $mappingOptions = [
        'contentType'                => OssClient::OSS_CONTENT_TYPE,
        'mimetype'                   => OssClient::OSS_CONTENT_TYPE,
        'contentLength'              => OssClient::OSS_LENGTH,
        'size'                       => OssClient::OSS_LENGTH,
        'headers'                    => OssClient::OSS_HEADERS,
    ];

    /**
     * Constructor.
     *
     * @param OssClient $client
     * @param string    $bucket
     * @param string    $prefix
     * @param array     $options
     */
    public function __construct(OssClient $client, $bucket, $prefix = '', array $options = [])
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get the Aliyun Oss Client bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get the Aliyun Oss Client instance.
     *
     * @return \OSS\OssClient
     */
    public function getClient()
    {
        return $this->client;
    }


    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);

    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if (! $this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $object = $this->applyPathPrefix($path);
        $newobject = $this->applyPathPrefix($newpath);

        try {
            $this->client->copyObject($this->bucket, $object, $this->bucket, $newobject);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }


    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     * TODO
     */
    public function readStream($path)
    {
        $response = $this->readObject($path);
        if ($response !== false) {
            $response['stream'] = $response['contents']->detach();
            unset($response['contents']);
        }
        return $response;
    }

    /**
     * Read an object and normalize the response.
     *
     * @param $path
     *
     * @return array|bool
     * TODO
     */
    protected function readObject($path)
    {
        $options = [
            'Bucket' => $this->bucket,
            'Key' => $this->applyPathPrefix($path),
        ];
        if (isset($this->options['@http'])) {
            $options['@http'] = $this->options['@http'];
        }
        $command = $this->s3Client->getCommand('getObject', $options + $this->options);
        try {
            /** @var Result $response */
            $response = $this->s3Client->execute($command);
        } catch (S3Exception $e) {
            return false;
        }
        return $this->normalizeResponse($response->toArray(), $path);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false
     */
    public function setVisibility($path, $visibility)
    {
        try {
            $this->client->putObjectAcl(
                $this->bucket,
                $this->applyPathPrefix($path),
                $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private'
            );
        } catch (OssException $exception) {
            return false;
        }
        return compact('path', 'visibility');
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        try {
            $acl = $this->client->getObjectAcl(
                $this->bucket,
                $this->applyPathPrefix($path)
            );
        } catch (OssException $exception) {
            return false;
        }
        return ['visibility' => $acl == 'public-read' ?
            AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE];
    }

    /**
     * Delete a file.
     *
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $this->client->deleteObject($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $list = $this->listContents($dirname, true);

        $objects = [];
        foreach ($list as $val) {
            if ($val['type'] === 'file') {
                $objects[] = $this->applyPathPrefix($val['path']);
            } else {
                $objects[] = $this->applyPathPrefix($val['path']).'/';
            }
        }

        try {
            $this->client->deleteObjects($this->bucket, $objects);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $object = $this->applyPathPrefix($dirname);
        $options = $this->getOptionsFromConfig($config);

        try {
            $this->client->createObjectDir($this->bucket, $object, $options);
        } catch (OssException $e) {
            return false;
        }

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $exists = $this->client->doesObjectExist($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return $exists;
    }

    /**
     * Read a file.
     *
     * @param string $path
     * @return array|false
     */
    public function read($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $contents = $this->client->getObject($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return compact('contents', 'path');
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = rtrim($this->applyPathPrefix($directory), '\\/');
        if ($directory) {
            $directory .= '/';
        }

        $bucket = $this->bucket;
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;
        $options = [
            'delimiter' => $delimiter,
            'prefix'    => $directory,
            'max-keys'  => $maxkeys,
            'marker'    => $nextMarker,
        ];

        $listObjectInfo = $this->client->listObjects($bucket, $options);

        $objectList = $listObjectInfo->getObjectList(); // 文件列表
        $prefixList = $listObjectInfo->getPrefixList(); // 目录列表

        $result = [];
        foreach ($objectList as $objectInfo) {
            if ($objectInfo->getSize() === 0 && $directory === $objectInfo->getKey()) {
                $result[] = [
                    'type'      => 'dir',
                    'path'      => $this->removePathPrefix(rtrim($objectInfo->getKey(), '/')),
                    'timestamp' => strtotime($objectInfo->getLastModified()),
                ];
                continue;
            }

            $result[] = [
                'type'      => 'file',
                'path'      => $this->removePathPrefix($objectInfo->getKey()),
                'timestamp' => strtotime($objectInfo->getLastModified()),
                'size'      => $objectInfo->getSize(),
            ];
        }

        foreach ($prefixList as $prefixInfo) {
            if ($recursive) {
                $next = $this->listContents($this->removePathPrefix($prefixInfo->getPrefix()), $recursive);
                $result = array_merge($result, $next);
            } else {
                $result[] = [
                    'type'      => 'dir',
                    'path'      => $this->removePathPrefix(rtrim($prefixInfo->getPrefix(), '/')),
                    'timestamp' => 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     * @throws \OSS\Core\OssException
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $result = $this->client->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return [
            'type'      => 'file',
            'dirname'   => Util::dirname($path),
            'path'      => $path,
            'timestamp' => strtotime($result['last-modified']),
            'mimetype'  => $result['content-type'],
            'size'      => $result['content-length'],
        ];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->updateStream($path, $resource, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        if ($visibility = $config->get('visibility')) {
            $options[OssClient::OSS_HEADERS] = [
                OssClient::OSS_OBJECT_ACL => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private',
            ];
        }

        try {
            $this->client->uploadFile($this->bucket, $object, stream_get_meta_data($resource)['uri'], $options);
        } catch (OssException $e) {
            return false;
        }

        return $this->normalizeResponse($options, $object);
    }

    /**
     * Upload an object.
     *
     * @param        $path
     * @param        $body
     * @param Config $config
     *
     * @return array
     */
    protected function upload($path, $body, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        if ($visibility = $config->get('visibility')) {
            $options[OssClient::OSS_HEADERS] = [
                OssClient::OSS_OBJECT_ACL => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private',
            ];
        }

        try {
            $this->client->putObject($this->bucket, $object, $body, $options);
        } catch (OssException $e) {
            return false;
        }

        return $this->normalizeResponse($options, $object);
    }

    /**
     * Get options from the config.
     *
     * @param Config $config
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;
        foreach (static::$mappingOptions as $option => $ossOption) {
            if (! $config->has($option)) {
                continue;
            }
            $options[$ossOption] = $config->get($option);
        }

        return $options;
    }

    /**
     * Normalize the object result array.
     *
     * @param array  $response
     * @param string $path
     *
     * @return array
     */
    protected function normalizeResponse(array $response, $path = null)
    {
        $result = [
            'path' => $path ?: $this->removePathPrefix(
                isset($response['Key']) ? $response['Key'] : $response['Prefix']
            ),
        ];
        $result = array_merge($result, Util::pathinfo($result['path']));
        if (isset($response['LastModified'])) {
            $result['timestamp'] = strtotime($response['LastModified']);
        }
        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');
            return $result;
        }
        return array_merge($result, Util::map($response, static::$resultMap), ['type' => 'file']);
    }

    public function getUrl($path, $options = [])
    {
        $options = array_merge($this->options, $options);
        $base = empty($options['cname']) ? $options['endpoint'] : $options['cname'];

        if (strpos($base, '//') !== false) {
            $segments = parse_url($base);
        } else {
            $segments = [
                'scheme' => 'http',
                'host' => $base,
            ];
        }
        if (empty($options['cname']) && strpos($segments['host'], $this->bucket) !== 0) {
            $segments['host'] = $this->bucket . '.' . $segments['host'];
        }
        return $segments['scheme'] . '://' . $segments['host'] . '/' . $this->applyPathPrefix($path);
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param  string $path
     * @param  null|\DateTimeInterface $expiration
     * @param  array $options
     * @return string
     */
    public function getTemporaryUrl($path, $expiration = null, $options = [])
    {
        $timeout = is_numeric($expiration) ? $expiration : $expiration->getTimestamp() - (new \DateTime('now'))->getTimestamp();

        $url = $this->client->signUrl($this->bucket, $this->applyPathPrefix($path), $timeout, OssClient::OSS_HTTP_GET, $options);

        $options = array_merge($this->options, $options);
        // Use cname host as much as possible
        if (!empty($options['cname'])) {
            $segments = parse_url($url);
            if (strpos($options['cname'], '//') !== false) {
                $csegs = parse_url($options['cname']);
                $segments['scheme'] = $csegs['scheme'];
                $segments['host'] = $csegs['host'];
            } else {
                $segments['host'] = $options['cname'];
            }
            $url = $segments['scheme'] . '://' . $segments['host'] . $segments['path'] . '?' . $segments['query'];
        }
        return $url;
    }
}
