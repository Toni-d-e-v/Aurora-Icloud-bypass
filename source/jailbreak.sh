echo "Getting ready to jailbreak..."
sudo ./exe/jk
echo "If jailbreak was successfull you should continue"
read -p "Press button R to restart jailbreak or press enter to continue"
if [ "$REPLY" == "r" ]; then
  echo "Restarting jailbreak..."
  sudo ./source/exe/jk
else 
  echo "You selected to continue"
  sudo ./source/exe/jk
fi
