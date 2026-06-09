@echo off

:: Set the installation directory for wkhtmltopdf
set INSTALL_DIR=%cd%\wkhtmltopdf

:: Create the directory if it doesn't exist
mkdir %INSTALL_DIR%

:: Download the wkhtmltopdf Windows binary
echo Downloading wkhtmltopdf...
powershell -Command "Invoke-WebRequest https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.6-1/wkhtmltox_0.12.6-1_win64.msi -OutFile %INSTALL_DIR%\wkhtmltox_0.12.6-1_win64.msi"

:: Install the MSI package
echo Installing wkhtmltopdf...
msiexec /i %INSTALL_DIR%\wkhtmltox_0.12.6-1_win64.msi /quiet

:: Clean up the installer file
del %INSTALL_DIR%\wkhtmltox_0.12.6-1_win64.msi

:: Verify installation
echo Verifying wkhtmltopdf installation...
%INSTALL_DIR%\wkhtmltopdf --version
