import serial

s=serial.Serial('/dev/serial/by-id/usb-STMicroelectronics_STM32_Virtual_COM_Port_1584324D3431-if00',38400,timeout=10)
while True:
    st=s.readline().decode('utf-8')
    if st[0:6]=='$GPRMC':
        print(st)
        f=open('gps.txt','w')
        f.write(st)
        f.close()
