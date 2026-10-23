@echo off
rem Double-click to start the Inventory system (no Apache needed).
rem The animated launcher and its checks live in start-server.ps1.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-server.ps1" %*
if errorlevel 1 pause
