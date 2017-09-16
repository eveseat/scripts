#!/bin/bash

# The list of packages for which we want to generate markdown formatted changelog
packages=( api console eveapi notifications services web )

# The root repository location
repository_directory=$(pwd)"/packages/eveseat/"

# The directory where all markdown formatted changelogs file will be generated
changelog_directory=$(pwd)"/packages/eveseat/docs/docs/changelogs/"

# check for named parameters
while getopts "hi:o:p:" opt
do
    case $opt in
        # help parameter
        h)
            echo "Git Markdown Changelog Generator"
            echo "make-md-changelog [-h] [-i path] [-o path] [-p 'package1 package2']"$'\n'
            echo "Options"
            echo "  -h"
            echo "    Display the current message"
            echo "  -i"
            echo "    Set a custom input directory"
            echo "  -o"
            echo "    Set a custom output directory"
            echo "  -p"
            echo "    Set a custom list of package"
            exit 0
            ;;
        # input directory parameter
        i)
            echo "Custom input directory has been set."

            # absolute directory
            if [[ "$OPTARG" = /* ]]
            then
                echo "Switching input directory to $OPTARG/"
                repository_directory=$OPTARG/
            # relative directory
            else
                echo "Switching input directory to $(pwd)/$OPTARG/"
                repository_directory=$(pwd)/$OPTARG/
            fi
            ;;
        # output directory parameter
        o)
            echo "Custom output directory has been set."

            if [[ "$OPTARG" = /* ]]
            then
                echo "Switching output directory to $OPTARG/"
                changelog_directory=$OPTARG/
            else
                echo "Switching output directory to $(pwd)/$OPTARG/"
                changelog_directory=$(pwd)/$OPTARG/
            fi
            ;;
        # packages parameter
        p)
            echo "Custom packages has been selected."
            echo "Update packages to ( $OPTARG )"

            packages=( $OPTARG )
            ;;
    esac
done

# ensure the source directory exist
if [[ ! -d "$repository_directory" ]]
then
    echo " [E] Unable to find the input directory $repository_directory !"
    exit 1
fi

# ensure the output directory exist
if [[ ! -d "$changelog_directory" ]]
then
    echo " [E] Unable to find the output directory $changelog_directory !"
    exit 1
fi

# iterate over packages
for package in "${packages[@]}"
do

    # ensure the package directory exist or skip
    if [[ ! -d "$repository_directory$package" ]]
    then
        echo " [W] Unable to find the package directory $repository_directory$package !"
        continue
    fi

    markdown_file=$changelog_directory$package.md
    oldtag=""

    # move to package directory
    cd "$repository_directory$package"

    echo "Generating changelog for $package, file output will be : $markdown_file"

    # list all tags from the package
    tags="$(git tag --sort=v:authordate:short)"

    # append header
    echo $'![SeAT](http://i.imgur.com/aPPOxSK.png)\n' > "$markdown_file"
    echo $"# $package change logs" >> "$markdown_file"
    echo $'Generated with: `git log --pretty=format:"%h%>(12)%ad %<(7)%aN%d %s" --date=short`\n' >> "$markdown_file"

    # iterate over git tags
    while read newtag
    do
        # add version header
        echo $"### $newtag" >> "$markdown_file"
        # open commit block
        echo $'```' >> "$markdown_file"

        # search for commits
        if [ -z "$oldtag" ]
        then
            git log --pretty=format:"%h%>(12)%ad %<(7)%aN%d %s" --date=short $newtag >> "$markdown_file" # list all commit up to the current tag
        else
            git log --pretty=format:"%h%>(12)%ad %<(7)%aN%d %s" --date=short $newtag ^$oldtag >> "$markdown_file" # make diff between previous and current tags
        fi

        # close commit block
        echo $'\n```' >> "$markdown_file"

        # buffer the tag for next diff iteration
        oldtag=$newtag
    done <<< "$tags"

done

echo "Done !"
