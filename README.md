# flysystem-aliyun-oss
league/flysystem-aws-s3-v3 like package but for aliyun oss(Object Storage Service)

完全实现Laravel文档所描述的所有API，包括setVisibility，put时使用resource等

## Tips:

上传的时候指定保存文件名：

```
Storage::put('a.txt', 'content', ['headers' => ['Content-Disposition' => 'attachment;filename=b.txt']])
```

下载的时候指定保存文件名：

```
Storage::temporaryUrl('a.txt', 60, ['response-content-disposition' => 'attachment;filename=b.txt'])
```

公共读取下载的时候是不可以指定保存的文件名的。
