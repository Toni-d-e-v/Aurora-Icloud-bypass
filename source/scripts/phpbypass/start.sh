

echo "CONNECT YOUR DEVICE TO YOUR PC"
read -p "Press enter to continue"

sudo apt-get install usbmuxd libimobiledevice6 libimobiledevice-utils
ideviceactivation activate -s http://127.0.0.1/activator.php 
