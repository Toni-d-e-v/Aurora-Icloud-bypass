

echo "CONNECT YOUR DEVICE TO YOUR PC"
read -p "Press enter to continue"
echo "starting php web server"
# check for php
if ! [ -x "$(command -v php)" ]; then
  echo 'Error: php is not installed.' >&2
  echo 'install php'
  sudo apt install php
else
  echo "php is installed"
fi
screen -d -m php -S localhost:8000 -t ./source/scripts/phpbypass/ 
sudo apt-get install screen usbmuxd libimobiledevice6 libimobiledevice-utils
ideviceactivation activate -s http://localhost:8000/activator.php 
