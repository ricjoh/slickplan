#!/bin/bash

FILENAME="$1"
JSPATH="$2"

if [[ "$FILENAME" == "" ]]; then
    echo "Usage: $0 <image-list-filename>"
    exit 1
fi

if [[ ! -f "$FILENAME" ]]; then
    echo "$0: \"$FILENAME\" does not exist!"
    exit 1
fi


files_name=$(basename "$FILENAME")
base=${files_name%%files-}
echo "files_name:$files_name"
echo "base:$base"


echo "JS Path:$JSPATH"

file_ok=1

tmp_prefix=$(mktemp -d)


# Read the file and sanity check the URL and path.
while read url path; do
    #echo
    #echo "url:$url"
    #echo "path:$path"

    this_line_ok=1
    
    if [[ "$url" =~ "^https://theferg.slickplan.com/" ]]; then
        this_line_ok=1
    fi
    

    if [[ $this_line_ok == 0 ]]; then
        echo "This line is not OK."
        file_ok=0
    fi
    # Make sure the URLs start with https and include "slickplan.com"
    # Make sure the paths start with /tmp/<base> where the filename is files-<base>


    if [[ $this_line_ok == "1" ]]; then
        filepath="$tmp_prefix/$path"

        #echo "getting url:[$url] path:[$path]"
        #echo "  to [$filepath]"

        mkdir -p $(dirname $filepath)
        wget -O $filepath $url
    fi


done <<< $(cat "$FILENAME")


if [[ $file_ok == 1 ]]; then

    echo "Everything is OK so far."

    echo rsync -rv "$tmp_prefix/" "ferguson@fergnas.fai.local:/share/CACHEDEV1_DATA/Jobs Server/$JSPATH/Slickplan Images"
    rsync -rv --chmod=Du=rwx,Dg=rwx,Do=rx,Fu=rw,Fg=rw,Fo=r "$tmp_prefix/" "ferguson@fergnas.fai.local:/share/CACHEDEV1_DATA/Jobs Server/$JSPATH/Slickplan Images"

    /bin/rm -r "$tmp_prefix"
    /bin/rm "$FILENAME"
fi
