Set objShell = CreateObject("WScript.Shell")
objShell.Run "cmd /K cd /d C:\xampp\htdocs\raceconnect-api && php -S localhost:8000 -t public"
Set objShell = Nothing
