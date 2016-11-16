# Sync a server folder content via lftp. Every 7 days a copy is done and archived
#./proc_lftp FTP_HOST FTP_USER FTP_PASS LOCAL_FOLDER EXCLUDE_SERVER_FOLDERS

#!/bin/bash
if [ "$1" = "" -o "$2" = "" -o "$3" = "" -o "$4" = ""  ]; then
    echo "error"
    exit;
fi
TODAY=$(date +%y%m%d)
HOST=$1 #
USER=$2 #
PASS=$3 #

if [ ! -z "$5" ]; then
	EXCLUDE=$(php -r 'foreach(explode(",","'$5'") as $b){ echo "--exclude-glob ".$b." ";}')
else
	EXCLUDE=""
fi



FTPURL="ftp://$USER:$PASS@$HOST"
ROOT="/home/backups/files/$4"

if [ ! -d "$ROOT" ]; then
	mkdir $ROOT
fi
CURRENT_COPY="/home/backups/files/$4/current"

if [ ! -d "$CURRENT_COPY" ]; then
	mkdir $CURRENT_COPY
fi
REMOTE_DIR="."

MAKE_BACKUP=1
for i in 1 2 3 4 5 6 7 
do
	DATE=$(date -d "$i day ago" '+%y%m%d');
	if [ -d "$ROOT/$DATE" ]; then
		MAKE_BACKUP=0
		break;
	fi	
done 

if [ $MAKE_BACKUP = 1 ]; then
	cp -r $CURRENT_COPY "$ROOT/$TODAY" # keep a copy/snapshot every 7 days
fi


lftp -c "set ftp:list-options -a;
set ftp:ssl-allow off;
set ssl:verify-certificate no;
open '$FTPURL';
lcd $CURRENT_COPY;
cd $REMOTE_DIR;
mirror --verbose $EXCLUDE";

echo 'ok';