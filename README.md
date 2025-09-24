mosquitto_pub -h test.mosquitto.org -p 1883 -t "ESP32-DEV-002/sensors" -m '{
  "sensors": [
    {
      "type": "thermal",
      "value": "22.8 celsius"
    },
    {
      "type": "humidity",
      "value": "45.0 percent"
    },
    {
      "type": "light",
      "value": "26 percent"
    },
    {
      "type": "potentiometer",
      "value": "100 percent"
    },
    {
      "type": "geolocation",
      "subtype": "latitude",
      "value": "39.527685"
    },
    {
      "type": "geolocation",
      "subtype": "longitude",
      "value": "-107.696663"
    }
  ],
  "timestamp": 30091
}'


mosquitto_pub -h broker.emqx.io -p 1883 -t "ESP32-DEV-001/sensors" -m '{
  "sensors": [
    {
      "type": "thermal",
      "value": "22.8 celsius"
    },
    {
      "type": "humidity",
      "value": "45.0 percent"
    },
    {
      "type": "light",
      "value": "26 percent"
    },
    {
      "type": "potentiometer",
      "value": "100 percent"
    },
    {
      "type": "geolocation",
      "subtype": "latitude",
      "value": "39.527685"
    },
    {
      "type": "geolocation",
      "subtype": "longitude",
      "value": "-107.696663"
    }
  ],
  "timestamp": 30091
}'




-23.5
0929162E5502466EACF8E70B48000F02

-5
0929162E5502326EACF8E70B48000F02
