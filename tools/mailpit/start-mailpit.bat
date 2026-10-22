@echo off
rem Mailpit: a test email server for RubricMind that works offline.
rem RubricMind sends its emails here (port 1025) instead of the internet,
rem and you read them at http://localhost:8025 like a mail inbox.
rem It only listens on this computer. Keep this window open while you test.

title Mailpit (RubricMind test inbox)
echo Mailpit is running.
echo   Inbox:  http://localhost:8025
echo   Close this window to stop it.
echo.
"%~dp0mailpit.exe" --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 --database "%~dp0mailpit.db"
