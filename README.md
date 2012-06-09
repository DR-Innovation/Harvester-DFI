Harvester-DFI
=============

This harvester connects to the open API of the Danish Film Institute and copies information on movies into a CHAOS service.

##Instructions
Once you have cloned the repo onto your machine, the CHAOS Client for PHP must be cloned from https://github.com/CHAOS-Community/CHAOS.Portal.Client-PHP into some local folder.
Set the following environment variables:
* **CHAOS_CLIENT_SRC**: The "src" folder which contains the CHAOS Client PHP sourcecode.
* **DFI_URL**: The URL of the DFI Open API. This is currently http://nationalfilmografien.service.dfi.dk/
* **CHAOS_CLIENT_GUID**: Some generated unique ID (can be generated at http://www.guidgenerator.com/)
* **CHAOS_URL**: A URL for some CHAOS service.
* **CHAOS_EMAIL**: Email for a user on the CHAOS service.
* **CHAOS_PASSWORD**: Password for a user on the CHAOS service.

##Requirements
* The CHAOS Client for PHP must be cloned from https://github.com/CHAOS-Community/CHAOS.Portal.Client-PHP into some local folder.
* PHP 5.3.5+ is required.
* The CURL plugin must be enabled in PHP.
* The iconv plugun must be enables in PHP (it is by default).

##License  
This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along with this program.  If not, see <[http://www.gnu.org/licenses/](http://www.gnu.org/licenses/)>.  