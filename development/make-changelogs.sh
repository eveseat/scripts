echo " + SeAT Changelog Generator"
echo

# Die if there are errors.
set -e

# -- Configurations --
#
# Directories
CHANGELOGS_DIR="changelogs"

# Repositories Location
MAIN_SEAT_REPO="."
API_REPO="packages/eveseat/api"
CONSOLE_REPO="packages/eveseat/console"
EVEAPI_REPO="packages/eveseat/eveapi"
NOTIFICATIONS_REPO="packages/eveseat/notifications"
SERVICES_REPO="packages/eveseat/services"
WEB_REPO="packages/eveseat/web"

# -- Setup --
#
# Get the current directory. We will use this as a reference
# when we need to move around the file system
CURRENT_DIRECTORY=`pwd`

# Check that the prereqs of this script is met. We cant continue
# without git and composer being available to run.
prereqs=(git)

for i in "${prereqs[@]}"; do
   command -v $i >/dev/null 2>&1 || { echo \
    "I require $i but it's not installed. Aborting." >&2; exit 1; }
done

# Prepare the changelogs directory.
echo " + Preparing Changelogs Directory"
if [[ ! -d "$CURRENT_DIRECTORY/$CHANGELOGS_DIR" ]]; then

    echo " * Unable to find the changelogs directory. Bailing!"
    exit 1
fi

# The command used to get changelogs
CHANGELOG_COMMAND="git log --pretty=format:\"%h%x09%an%x09%ad%x09%s\""

echo " + Generating changelogs for $MAIN_SEAT_REPO"
cd $MAIN_SEAT_REPO
MAIN_SEAT_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $API_REPO"
cd $API_REPO
API_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $CONSOLE_REPO"
cd $CONSOLE_REPO
CONSOLE_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $EVEAPI_REPO"
cd $EVEAPI_REPO
EVEAPI_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $NOTIFICATIONS_REPO"
cd $NOTIFICATIONS_REPO
NOTIFICATIONS_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $SERVICES_REPO"
cd $SERVICES_REPO
SERVICES_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Generating changelogs for $WEB_REPO"
cd $WEB_REPO
WEB_CHANGELOG=`eval $CHANGELOG_COMMAND`
cd $CURRENT_DIRECTORY

echo " + Writing Changelogs"
cd $CHANGELOGS_DIR
echo "$MAIN_SEAT_CHANGELOG" > seat.txt
echo "$API_CHANGELOG" > api.txt
echo "$CONSOLE_CHANGELOG" > console.txt
echo "$EVEAPI_CHANGELOG" > eveapi.txt
echo "$NOTIFICATIONS_CHANGELOG" > notifications.txt
echo "$SERVICES_CHANGELOG" > services.txt
echo "$WEB_CHANGELOG" > web.txt

echo " + Writing Combined Changelog"
echo "$MAIN_SEAT_CHANGELOG" > all.txt
echo "$API_CHANGELOG" >> all.txt
echo "$CONSOLE_CHANGELOG" >> all.txt
echo "$EVEAPI_CHANGELOG" >> all.txt
echo "$NOTIFICATIONS_CHANGELOG" >> all.txt
echo "$SERVICES_CHANGELOG" >> all.txt
echo "$WEB_CHANGELOG" >> all.txt
cd $CURRENT_DIRECTORY

echo " + Done"

