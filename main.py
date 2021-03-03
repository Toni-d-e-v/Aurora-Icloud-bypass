import PySimpleGUI as sg
import os
sg.theme('Light Blue 3')
os.system("chmod +x jk") # chmod jailbreak

def button2():
    print('Starting')
    os.system("python3 bypass.py")
    os.system("echo done")

def button1():
    print('Launching jailbreak')
    os.system("cd jailbreak")
    os.system("sudo ./jk")
    os.system("echo done")



dispatch_dictionary = {'1':button1, '2':button2}


layout = [[sg.Text('Please click a button', auto_size_text=True)],
          [sg.Text('1. Jailbreak', auto_size_text=True)],
          [sg.Text('2. bypass', auto_size_text=True)],
          [sg.Text('3. Sim fix coming soon', auto_size_text=True)],
          [sg.Button('1'), sg.Button('2'), sg.Button('3'), sg.Quit()]]


window = sg.Window('Toni.Dev`s Icloud Unlock (linux only)', layout)

while True:
   
    event, value = window.read()
    if event in ('Quit', sg.WIN_CLOSED):
        break
    
    if event in dispatch_dictionary:
        func_to_call = dispatch_dictionary[event]   
        func_to_call()
    else:
        print('Event {} not in dispatch dictionary'.format(event))

window.close()

    
sg.popup_ok('Done')
