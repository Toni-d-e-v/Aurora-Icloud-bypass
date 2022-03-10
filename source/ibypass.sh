# icloud unlock Aurora: 0.1
echo "Welcome to icloud unlock Aurora: 0.1, github.com/Toni-d-e-v/Icloud-Unlocker/"
echo "To make this exploit work we will launch checkrain jailbreak please follow the instructions and do not close the terminal"
echo "If you want to exit the script press ctrl + c"
read -p "Press enter to continue"
echo "Checking for python3..."
if ! [ -x "$(command -v python3)" ]; then
  echo 'Error: python3 is not installed.' >&2
  exit 1
else
  echo "python3 is installed"
fi
echo "Checking for pip3..."
if ! [ -x "$(command -v pip3)" ]; then
  echo 'Error: pip3 is not installed.' >&2
  exit 1
else
  echo "pip3 is installed"
fi
echo "Getting ready to jailbreak..."pip paramiko
sudo ./source/exe/jk
echo "If jailbreak was successfull you should continue"
read -p "Press button R to restart jailbreak or press enter to continue"
if [ "$REPLY" == "r" ]; then
  echo "Restarting jailbreak..."
  sudo ./source/exe/jk
else 
  echo "You selected to continue"
fi
echo "Checking for python scripts..."
if [ -f "./source/scripts/bypass.py" ]; then
  echo "bypass.py found"
else
  echo "bypass.py not found"
  exit 1
fi
if [ -f "./source/scripts/usbmux.py" ]; then
  echo "usbmux.py found"
else
  echo "usbmux.py not found"
  exit 1
fi
echo "Installing required libs..."
sudo pip3 install paramiko
echo "All files are found"
echo "Good luck"
echo "Starting python scripts..."
echo "Let the script finish wait 2-5 minutes and your device should be unlocked"
echo "ON COMPLEATE REBOOT DEVICE MAY BE LOCKED, AND SIM CARD WONT WORK"
echo "ON SOME IOS VERSIONS THIS WONT WORK AND PLEASE DO NOT UPDATE, IF IT DOES NOT WORK DOWNGRADE IOS"
# coutdown
for i in {1..5}
do
  echo "Starting at 5 $i"
  sleep 1
done
python3 ./source/scripts/bypass.py
exit 0
