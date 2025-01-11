#!/bin/bash
# Deploy to production
rsync -avz --exclude-from='.gitignore' ./ wpxcxfmy@burningtocook.com:public_html/ 