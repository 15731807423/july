cd system && rename .env.production .env && composer install && cd ../front-vue && npm install && cd ../ && echo %date:~0,10% %time%>time.txt && del run.bat