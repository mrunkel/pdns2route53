# pdns2route53
PHP Script to generate [cli53](https://github.com/barnybug/cli53) commands from a [PowerDNS](https://www.powerdns.com/) MySQL database

I used this script to upload some PowerDNS MySQL DNS zones to Amazon's [Route53](https://aws.amazon.com/route53/) service.

**This script doesn't actually load anything into Amazon.

It only generates the required cli53 commands to load the DNS zone.**

*cli53 must be already installed*

Instructions:
1. Clone Repo
`git clone git@github.com:mrunkel/pdns2route53.git`
2. Change directories
`cd pdns2route53`
3. Make the PHP script executable
`chmod a+x`
4. Run the script for *zone.com* and pipe it to bash `./pdns2route53 -z zone.com | bash`
