# docker-php

#### 安装启动docker
1. yum update -y
2. yum -y install docker
3. service start docker

#### 设置镜像
1. vi /etc/docker/daemon.json
{
  "registry-mirrors": ["https://aj2rgad5.mirror.aliyuncs.com"]
}
2. service restart docker

#### 安装启动docker-compose
1. curl -L https://github.com/docker/compose/releases/download/1.8.1/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose
2. chmod +x /usr/local/bin/docker-compose
3. docker-compose --version

#### 拉取docker-php
git clone https://gitee.com/hyperions/docker-php.git

#### 一键部署环境
cd /server/compose.dockerfiles
docker-compose up -d --build

#### 路径说明
假设docker-php的目录是：/usr/local/docker
项目库路径：/usr/local/docker/server/www
nginx配置目录：/usr/local/docker/server/nginx
php配置目录：/usr/local/docker/server/php
mysql数据目录：/usr/local/docker/server/mysql    默认密码：123456
