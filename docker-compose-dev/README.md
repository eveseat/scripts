# docker-compose for SeAT development

## setup
- Clone this repository.
- Copy out the `docker-compose-dev` folder somewhere  convenient or just `cd` to it.
- If you have an existing SeAT project, set its **full path** in the `.env` file, otherwise, run `bash prepare-source.sh`.
- Run `docker-compose up -d`
- Give the containers some time to start up.
## creating the eve-online SSO application
- go to https://developers.eveonline.com/applications and create a new application selecting `Authentication & API Access` then select all scopes.
## configuring .env file with the app url and SSO settings
- `cd` into seat/
- Use a text editor to edit `.env` , change your `APP_URL=` to the URL seat will live on, enter your `EVE_CLIENT_ID` `EVE_CLIENT_SECRET` and `EVE_CALLBACK_URL` from the application you made. Save and close the file.
## Setup admin account
- run `docker exec -it seat-app php artisan seat:admin:login` and copy the link provided, paste this into your browser, log in using your eve SSO, and you will have created your admin account.




## notes
You can follow the install of the seat-app container by issuing the command `docker logs -f seat-app`.
When you see `[01-May-2018 10:10:35] NOTICE: ready to handle connections`
Seat is installed and ready to go. And you may now proceed with setting up the admin account.
