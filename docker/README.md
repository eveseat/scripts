# seat single dockerfile

This dockerfile is for a single container installation of SeAT. Probably only really useful for demo purposes.

## build

After cloning the repo, build me with:

```
docker build -t eveseat/seat .
```

## run

After build, run me with:

```
docker run -d --name seat -p 80:80 -it eveseat/seat
```

## shell

After starting the container, drop into a shell with:

```
docker exec -it seat bash
```
