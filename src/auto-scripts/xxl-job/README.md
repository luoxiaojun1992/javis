# xxl-job cron 备份为 linux cron

## 注意
1. 任务计划时间设置不要设置秒和年，因为 linux cron 不支持秒和年
2. glue shell 脚本源码备注(非任务描述)不要使用中文

## 操作步骤

### 更改xxl-job数据库连接
```
vi config/db.php

[
    'dsn' => 'mysql:dbname=[dbname];host=[dbhost];charset=utf8mb4',
    'username' => '[dbuser]',
    'passwd' => '[dbpass]',
]
```

### 安装composer依赖

```
composer install -vvv
```

### 获取cron 和 脚本
```
php convertToCrontab.php
cd output/
cat crontab.txt
ls shell
chmod +x shell/*
```

### 导入到linux cron
```
cat output/crontab.txt >>  /var/spool/cron/root
# crontab.txt 里面注释的内容为xxl停用的cron
tail -f /var/log/cron
```