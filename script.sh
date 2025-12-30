#!/usr/bin/env zsh

docker exec -i some-mysql mysql -u root -p042002 <<EOF
USE une_boutique;
SELECT * FROM users;
EOF
