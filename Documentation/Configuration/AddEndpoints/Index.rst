.. include:: ../Includes.txt

.. _addendpoints:

================
Add the Endpoint
================

To add the endpoint, switch into list-view, chose the storage-page (see plugin.tx_newt.persistence.storagePid)

Click the plus-button, to add a new record and chose :code:`Endpoint` under :code:`Newt`

Enter the Name and the description, the Client will see this labels.

Chose the Endpoint Class :code:`Swisscode\Newt4Dce\Newt\DceEndpoint` from the list


Endpoint Options
----------------

Add the needed options to tell the Endpoint which DCE-Element to use and what fields are used to render the item in the App

.. figure:: ../../Images/backend-04.jpg
   :class: with-shadow
   :alt: Options of the Endpoint


Page UID
--------

If you fill in the Page UID, the records created with Newt will be stored on this page.


Methods
-------

Add at least one Method

If you use CRUD and use image/file upload, please add the :code:`READ` method!

.. figure:: ../../Images/backend-02.jpg
   :class: with-shadow
   :alt: Methods of this endpoint

Admin-Users will see all endpoints, all other users have to be granted by BE-Groups
There is also a frontend-plugin available, if you like to grant the access to Newt for any FE-Users, you may add a FE-Group as well

.. figure:: ../../Images/backend-03.jpg
   :class: with-shadow
   :alt: Grant access with groups

When this record is saved, the endpoint is configured, and the client is able to read the configuration of this endpoint
and is able to create new records as you defined in the endpoint.
