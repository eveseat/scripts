# docker-compose for SeAT development

This folder contains a development compose file, tyipcally used for local SeAT development.

## setup

- Clone this repository.
- Copy out the `docker-compose-dev` folder somewhere convenient or just `cd` to it.
- If you have an existing SeAT project, set its **full path** in the `.env` file, otherwise, run `bash prepare-source.sh`.
- Run `docker-compose up -d`
- Give the containers some time to start up.

## webserver host

The webserver used is Traefik, which will serve the SeAT Web UI at <https://web.seat.local/>. Just browsing to localhost will not work as this relies on SNI. To reach the UI, add new hosts entries to your hosts file (like `/etc/hosts`):

```text
127.0.0.1   web.seat.local
127.0.0.1   traefik.seat.local
```

## creating the eve-online SSO application

Go to <https://developers.eveonline.com/applications> and create a new application selecting `Authentication & API Access` then select all scopes.

## configuring .env file with the app url and SSO settings

Use a text editor to edit `.env` in this directory and change your `WEB_DOMAIN=` to the domain seat will live on (for the dev env you can leave this as is). Enter your `EVE_CLIENT_ID` `EVE_CLIENT_SECRET` from the application you made. Save and close the file.

## Setup admin account

Login with your EVE account into the instance. Next, run `docker-compose exec seat-web php artisan seat:admin:login` and copy / paste the link provided into your browser. Finally, go to Settings -> Users -> Edit your user and tick the "Administrator" toggle.

### notes

You can follow the install of the seat-web container by issuing the `docker logs -f seat-app` command. When you see something like the below, seat should be ready to go.

```text
seat-web_1     | [Sat Aug 15 17:46:13.327701 2020] [core:notice] [pid 32] AH00094: Command line: 'apache2 -D FOREGROUND'
```
