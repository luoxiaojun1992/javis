#! /usr/bin/env python

# coding=utf-8

from datetime import datetime
import matplotlib.pyplot as plt
import csv
import sys

with open(sys.argv[1],'rb') as csvfile:
    reader = csv.DictReader(csvfile)
    reportDate = [datetime.strptime(row['report_date'], '%Y-%m-%d').date() for row in reader]

with open(sys.argv[1],'rb') as csvfile:
    reader = csv.DictReader(csvfile)
    successMaxDuration = [float(row['success_max_duration']) for row in reader]

with open(sys.argv[1],'rb') as csvfile:
    reader = csv.DictReader(csvfile)
    serverErrorTimes = [float(row['server_error_times']) for row in reader]

with open(sys.argv[1],'rb') as csvfile:
    reader = csv.DictReader(csvfile)
    clientErrorTimes = [float(row['client_error_times']) for row in reader]

plt.figure(1)
plt.title('Max Success Duration')
plt.xlabel('date')
plt.ylabel('duration')
plt.plot(reportDate, successMaxDuration)

plt.figure(2)
plt.title('Server Error Times')
plt.xlabel('date')
plt.ylabel('times')
plt.plot(reportDate, serverErrorTimes)

plt.figure(3)
plt.title('Client Error Times')
plt.xlabel('date')
plt.ylabel('times')
plt.plot(reportDate, clientErrorTimes)

plt.show()
