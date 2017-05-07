# docker-compose for SeAT development
### Build image
```
git clone git@github.com:eveseat/scripts.git
cd scripts
docker-compose build
```
This will take some times but there is only 1 image to build (mysql and redis are pulled already built from the docker hub).
### Run
```
docker-compose up -d
```
3 containers will be created. The first time the `eveseat_app` is ran, it will migrate all data in the DB so it can take some times. You can monitor this by using the command `docker logs eveseat_app`.

After that, your SeAT instance should be available at http://localhost:8080 (`admin:password`). The MySQL database should be available too at localhost on port 3366 (`root:password`).
To properly stop the SeAT instance, use : `docker-compose stop`.

### Shell
To connect to the container, use : 
```
docker exec -it eveseat_app /bin/bash
```
`nano` is installed on this container to edit files.
### Volumes for development
By default, the `scripts/docker/packages` directory will be mounted to `/var/www/seat/packages` in the container allowing you to develop your package outside of the container. 
You can change this configuration in the `docker-compose.yml` :
```
    volumes:
      - ./packages:/var/www/seat/packages
```
### Persistence
Until you explicitly delete containers, all datas are persistents even if you stop/start the containers.