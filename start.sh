#!/usr/bin/env bash

# Renders a text based list of options that can be selected by the
# user using up, down and enter keys and returns the chosen option.
#
#   Arguments   : list of options, maximum of 256
#                 "opt1" "opt2" ...
#   Return value: selected index (0 for opt1, 1 for opt2 ...)
function select_option {

    # little helpers for terminal print control and key input
    ESC=$( printf "\033")
    cursor_blink_on()  { printf "$ESC[?25h"; }
    cursor_blink_off() { printf "$ESC[?25l"; }
    cursor_to()        { printf "$ESC[$1;${2:-1}H"; }
    print_option()     { printf "   $1 "; }
    print_selected()   { printf "  $ESC[7m $1 $ESC[27m"; }
    get_cursor_row()   { IFS=';' read -sdR -p $'\E[6n' ROW COL; echo ${ROW#*[}; }
    key_input()        { read -s -n3 key 2>/dev/null >&2
                         if [[ $key = $ESC[A ]]; then echo up;    fi
                         if [[ $key = $ESC[B ]]; then echo down;  fi
                         if [[ $key = ""     ]]; then echo enter; fi; }

    # initially print empty new lines (scroll down if at bottom of screen)
    for opt; do printf "\n"; done

    # determine current screen position for overwriting the options
    local lastrow=`get_cursor_row`
    local startrow=$(($lastrow - $#))

    # ensure cursor and input echoing back on upon a ctrl+c during read -s
    trap "cursor_blink_on; stty echo; printf '\n'; exit" 2
    cursor_blink_off

    local selected=0
    while true; do
        # print options by overwriting the last lines
        local idx=0
        for opt; do
            cursor_to $(($startrow + $idx))
            if [ $idx -eq $selected ]; then
                print_selected "$opt"
            else
                print_option "$opt"
            fi
            ((idx++))
        done

        # user key control
        case `key_input` in
            enter) break;;
            up)    ((selected--));
                   if [ $selected -lt 0 ]; then selected=$(($# - 1)); fi;;
            down)  ((selected++));
                   if [ $selected -ge $# ]; then selected=0; fi;;
        esac
    done

    # cursor position back to normal
    cursor_to $lastrow
    printf "\n"
    cursor_blink_on

    return $selected
}
echo "Welcome to Aurora IOS tool, github.com/Toni-d-e-v/Icloud-Unlocker/"
echo "We are not responsible for any damage done to your device"
echo "Features: Bypass Activation lock, Remove old icloud account, root shell to Idevice, Jailbreak the device"
echo "Select one option using up/down keys and enter to confirm:"
echo

options=( "Icloud bypass IOS 12.3-13.2.3! NO SIM CARD (AUTOMATIC ONE)" "newPHP ICLOUD BYPASS WITH SIM  " "Removes old icloud account conected to the device  (JAILBREAK REQUIRED)" "Jailbreak the device" "Exit")

select_option "${options[@]}"
choice=$?

echo "Choosen = $choice"

echo "Launching selected option..."
if [ $choice = "0" ]; then
   clear
   chmod +x ./source/ibypass.sh
   ./source/ibypass.sh
elif [ $choice = "1" ]; then
    clear
    chmod +x ./source/php.sh
    ./source/php.sh
elif [ $choice = "2" ]; then
    clear
    chmod +x ./source/rm_oldicloud.sh
    ./source/rm_oldicloud.sh
elif [ $choice = "3" ]; then
    clear
    chmod +x ./source/jailbreak.sh
    ./source/jailbreak.sh
else
    echo "Exiting..."
    clear
    echo "Bye!"
    exit
fi