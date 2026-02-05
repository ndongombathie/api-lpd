#!/usr/bin/env zsh
mysql -u root -ppassword <<EOF
USE une_boutique;
SELECT * FROM users;
EOF
