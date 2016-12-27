import psycopg2
import datetime
import pytz
from pandas.io.sql import read_sql
from pyiem.network import Table as NetworkTable
from pyiem.nws import vtec
from pyiem.util import get_autoplot_context


def get_description():
    """ Return a dict describing how to call this plotter """
    d = dict()
    d['cache'] = 600
    d['data'] = True
    d['description'] = """Gaant chart of watch, warning, and advisories issued
    by an NWS Forecast Office for a start date and number of days of your
    choice. The duration of the individual alert is the maximum found between
    the earliest issuance and latest expiration."""
    d['arguments'] = [
        dict(type='networkselect', name='station', network='WFO',
             default='DMX', label='Select WFO:'),
        dict(type='date', name='sdate', default='2015/01/01',
             label='Start Date:',
             min="2005/10/01"),  # Comes back to python as yyyy-mm-dd
        dict(type='int', name='days', default=10,
             label='Number of Days in Plot'),
    ]
    return d


def plotter(fdict):
    """ Go """
    import matplotlib
    matplotlib.use('agg')
    import matplotlib.pyplot as plt
    import matplotlib.dates as mdates
    pgconn = psycopg2.connect(database='postgis', host='iemdb', user='nobody')
    ctx = get_autoplot_context(fdict, get_description())
    station = ctx['station']
    sts = ctx['sdate']
    sts = datetime.datetime(sts.year, sts.month, sts.day)
    days = ctx['days']

    nt = NetworkTable('WFO')
    tz = pytz.timezone(nt.sts[station]['tzname'])

    sts = sts.replace(tzinfo=tz)
    ets = sts + datetime.timedelta(days=days)
    df = read_sql("""
     SELECT phenomena, significance, eventid,
     min(issue at time zone 'UTC') as minissue,
     max(expire at time zone 'UTC') as maxexpire,
     max(coalesce(init_expire, expire) at time zone 'UTC') as maxinitexpire
     from warnings
     WHERE wfo = %s and issue > %s and issue < %s
     GROUP by phenomena, significance, eventid
     ORDER by minissue ASC
    """, pgconn, params=(station, sts, ets), index_col=None)

    events = []
    labels = []
    types = []
    for i, row in df.iterrows():
        endts = max(row[4],
                    row[5]).replace(tzinfo=pytz.timezone("UTC"))
        events.append((row[3].replace(tzinfo=pytz.timezone("UTC")),
                       endts,
                       row[2]))
        labels.append("%s %s" % (vtec._phenDict[row[0]],
                                 vtec._sigDict[row[1]]))
        types.append("%s.%s" % (row[0], row[1]))

    # If we have lots of WWA, we need to expand vertically a bunch, lets
    # assume we can plot 5 WAA per 100 pixels
    if len(events) > 20:
        height = int(len(events) / 6.0) + 1
        (fig, ax) = plt.subplots(figsize=(8, height))
        fontsize = 8
    else:
        (fig, ax) = plt.subplots()
        fontsize = 10

    used = []

    def get_label(i):
        if types[i] in used:
            return ''
        used.append(types[i])
        return "%s (%s)" % (labels[i], types[i])

    halfway = sts + datetime.timedelta(days=days/2.)

    for i, e in enumerate(events):
        secs = abs((e[1]-e[0]).days * 86400.0 + (e[1]-e[0]).seconds)
        ax.barh(i+0.6, secs / 86400.0, left=e[0],
                fc=vtec.NWS_COLORS.get(types[i], 'k'),
                ec=vtec.NWS_COLORS.get(types[i], 'k'), label=get_label(i))
        align = 'left'
        xpos = e[0] + datetime.timedelta(seconds=secs + 3600)
        if xpos > halfway:
            align = 'right'
            xpos = e[0] - datetime.timedelta(minutes=90)
        textcolor = vtec.NWS_COLORS.get(
                        types[i] if types[i] != 'TO.A' else 'X', 'k')
        ax.text(xpos, i+1,
                labels[i].replace("Weather", "Wx") + " " + str(e[2]),
                color=textcolor, ha=align,
                va='center', bbox=dict(color='white', boxstyle='square,pad=0'),
                fontsize=fontsize)

    ax.set_ylabel("Sequential Product Number")
    ax.set_title(("%s-%s NWS %s\nissued Watch/Warning/Advisories"
                  ) % (sts.strftime("%-d %b %Y"), ets.strftime("%-d %b %Y"),
                       nt.sts[station]['name']))
    ax.set_ylim(0.4, len(events)+1)
    ax.xaxis.set_minor_locator(mdates.DayLocator(interval=1, tz=tz))
    xinterval = int(days / 7) + 1
    ax.xaxis.set_major_locator(mdates.DayLocator(interval=xinterval, tz=tz))
    ax.xaxis.set_major_formatter(mdates.DateFormatter('%-d %b', tz=tz))

    ax.grid(True)

    ax.set_xlim(sts, ets)

    # Shrink current axis's height by 10% on the bottom
    box = ax.get_position()
    ax.set_position([box.x0, box.y0 + box.height * 0.2,
                     box.width, box.height * 0.8])

    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.1),
              fancybox=True, shadow=True, ncol=3, scatterpoints=1, fontsize=8)

    return fig, df
