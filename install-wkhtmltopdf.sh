#!/bin/bash

# Directory where wkhtmltopdf will be installed (inside your project)
INSTALL_DIR="./wkhtmltopdf"

# Create the directory if it doesn't exist
mkdir -p $INSTALL_DIR

# Download the precompiled wkhtmltopdf binary (make sure to get the correct version)
wget -q https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.buster_amd64.deb -P $INSTALL_DIR

# Install necessary dependencies (only for Ubuntu/Debian)
sudo apt-get install -y xfonts-75dpi xfonts-base

# Install the .deb package
sudo dpkg -i $INSTALL_DIR/wkhtmltox_0.12.6-1.buster_amd64.deb

# Clean up
rm $INSTALL_DIR/wkhtmltox_0.12.6-1.buster_amd64.deb

# Verify installation
$INSTALL_DIR/wkhtmltopdf --version
