#!/bin/bash

msg=$1

git pull && git add . && git commit -m "Sam: $msg" && git push


