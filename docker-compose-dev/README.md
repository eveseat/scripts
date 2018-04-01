# docker-compose for SeAT development

## setup
- Clone this repository.
- Copy out the `docker-compose-dev` folder somewhere  convenient or just `cd` to it.
- If you have an existing SeAT project, set its **full path** in the `.env` file, otherwise, run `bash prepare-source.sh`.
- Run `docker-compose --project-name seat-dev up -d`
- Give the containers some time to start up.
