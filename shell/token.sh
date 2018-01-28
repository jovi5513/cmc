!/bin/bash

# ~/crypto-project/token.sh
cd ~/crypto-project/
git pull 
cd shell
php token.php
cd ..
sh sam-push.sh
