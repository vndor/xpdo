%~d0
cd %~dp0
@echo off
REM phpunit must be installed. See more : https://phpunit.de/getting-started/phpunit-5.html
REM bat file can run via CMD, open WIN+R CMD and RUN this file "startTest.bat"
@echo on
phpunit -c phpunit.xml --bootstrap bootstrap_autoload.php . --debug