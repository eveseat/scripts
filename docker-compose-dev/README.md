# docker-compose for SeAT development

## setup
- Clone this repository.
- Copy out the `docker-compose-dev` folder somewhere  convenient or just `cd` to it.
- If you have an existing SeAT project, set its **full path** in the `.env` file, otherwise, run `bash prepare-source.sh`.
- Run `docker-compose --project-name seat-dev up -d`
- Give the containers some time to start up.
- go to https://developers.eveonline.com/applications and create a new application selecting `Authentication & API Access` then select all scopes.
- `cd` into seat/
- run `sudo nano .env` to open the .env file, change your `APP_URL=` to the URL seat will live on, enter your `EVE_CLIENT_ID` `` `EVE_CLIENT_SECRET` and `EVE_CALLBACK_URL` from the application you made a few steps ago. Save and close the file.
- run `docker exec -it seat-app php artisan seat:admin:login` and copy the link provided, paste this into your browser and you will have created your admin account.




## notes
You can follow the install of the seat-app container by issuing the command `docker logs -f seat-app`.
