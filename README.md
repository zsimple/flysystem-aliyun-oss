# flysystem-aliyun-oss
league/flysystem-aws-s3-v3 like package but for aliyun oss(Object Storage Service)

完全实现Laravel文档所描述的所有API，包括setVisibility，put时使用resource等

## Tips:

上传的时候指定文件下载时的文件名(Laravel 5.4以后可用，之前版本的put方法第三个参数只能设置public/private)：

```
Storage::put('a.txt', 'content', ['headers' => ['Content-Disposition' => 'attachment;filename=b.txt']])
```

下载的时候临时指定保存文件名：

```
Storage::temporaryUrl('a.txt', 60, ['response-content-disposition' => 'attachment;filename=b.txt'])
```

公共读取权限的文件下载的时候是不可以指定保存文件名的。
