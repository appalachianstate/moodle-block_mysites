## MySites block plugin for Moodle

### Description
This block plugin provides users with navigation links to courses on external sites in which they have an enrollment. Provided they have the necessary role capabilities, users can also submit a request to have a course on the external site backed up. The resulting backup file will be uploaded to the site from which the request was made and placed in the user's private backup files area. This amounts to be a convenience saving the user the usual steps necesary in a _backup -> download -> upload -> restore_ workflow.

The plugin requires that the usernames are consistent across all the sites configured, and for the best user experience all the sites should use SSO authentication.
### Configuration
#### Web Service
After the plugin is installed its external web services must be enabled, a user account must be associated, and a security token generated; also, file uploads for the web service must be enabled on every site from which users will issue backup requests and expect the resulting file to be returned. See Moodle [documentation](https://docs.moodle.org/en/Using_web_services) on enabling and configuring external web services.
#### Sites
Configure each site by first designating its ID, and once set do not change it. Then enter the list of external sites with which the current one being configured will interact. Each line in the list represents a single external site and contains that site's:

 - ID (10 chars a-z0-9)
 - display name
 - ws key (32 chars 0-9a-f)
 - base URL
 - an upload filesize limit (e.g. 200M)
 - indicator (Y/N) whether to send requests to this site
 - indicator (Y/N) whether to accept requests from this site

### Operation
#### Theme
The plugin renders markup that makes use of Bootstrap, so it is expected you use the Boost theme or one derived from it.
#### Caching
Each user's list of courses and backups from the external sites is cached in mdl_block_mysites. A scheduled task (runs by default every 10 minutes) removes stale cache entries. A refresh link can be used so a user can force the cache to be cleared early, but only for themselves.
