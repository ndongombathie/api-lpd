#!/usr/bin/env zsh
mysql -u root -p042002 <<EOF
USE une_boutique;
SELECT * FROM users;
EOF
